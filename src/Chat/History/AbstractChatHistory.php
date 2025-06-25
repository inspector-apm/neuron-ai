<?php

declare(strict_types=1);

namespace NeuronAI\Chat\History;

use NeuronAI\Chat\Attachments\Document;
use NeuronAI\Chat\Attachments\Image;
use NeuronAI\Chat\Enums\AttachmentContentType;
use NeuronAI\Chat\Enums\AttachmentType;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolCallResultMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\Tool;

abstract class AbstractChatHistory implements ChatHistoryInterface
{
    protected array $history = [];

    public function __construct(protected int $contextWindow = 50000)
    {
    }

    protected function updateUsedTokens(Message $message): void
    {
        if ($message->getUsage()) {
            // For every new message, we store only the marginal contribution of input tokens
            // of the latest interactions.
            $previousInputConsumption = \array_reduce($this->history, function (int $carry, Message $message): int {
                if ($message->getUsage()) {
                    $carry += $message->getUsage()->inputTokens;
                }
                return $carry;
            }, 0);

            // Subtract the previous input consumption.
            $message->getUsage()->inputTokens -= $previousInputConsumption;
        }
    }

    public function addMessage(Message $message): ChatHistoryInterface
    {
        $this->updateUsedTokens($message);

        $this->history[] = $message;
        $this->storeMessage($message);

        $this->cutHistoryToContextWindow();

        return $this;
    }

    abstract protected function storeMessage(Message $message): ChatHistoryInterface;

    public function getMessages(): array
    {
        return $this->history;
    }

    public function getLastMessage(): Message
    {
        return \end($this->history);
    }

    abstract public function removeOldestMessage(): ChatHistoryInterface;

    abstract protected function clear(): ChatHistoryInterface;

    public function flushAll(): ChatHistoryInterface
    {
        $this->clear();
        $this->history = [];
        return $this;
    }

    public function calculateTotalUsage(): int
    {
        return \array_reduce($this->history, function (int $carry, Message $message) {
            if ($message->getUsage()) {
                $carry += $message->getUsage()->getTotal();
            }

            return $carry;
        }, 0);
    }

    protected function cutHistoryToContextWindow(): void
    {
        if ($this->getFreeMemory() >= 0) {
            return;
        }

        // Cut old messages
        do {
            $this->removeOldestMessage();
            if (\array_shift($this->history) === null) {
                break;
            }
        } while ($this->getFreeMemory() < 0);
    }

    public function getFreeMemory(): int
    {
        return $this->contextWindow - $this->calculateTotalUsage();
    }

    public function jsonSerialize(): array
    {
        return $this->getMessages();
    }

    protected function deserializeMessages(array $messages): array
    {
        return \array_map(fn (array $message) => match ($message['type'] ?? null) {
            'tool_call' => $this->deserializeToolCall($message),
            'tool_call_result' => $this->deserializeToolCallResult($message),
            default => $this->deserializeMessage($message),
        }, $messages);
    }

    protected function deserializeMessage(array $message): Message
    {
        $messageRole = MessageRole::from($message['role']);
        $messageContent = $message['content'] ?? null;

        $item = match ($messageRole) {
            MessageRole::ASSISTANT => new AssistantMessage($messageContent),
            MessageRole::USER => new UserMessage($messageContent),
            default => new Message($messageRole, $messageContent)
        };

        $this->deserializeMeta($message, $item);

        return $item;
    }

    protected function deserializeToolCall(array $message): ToolCallMessage
    {
        $tools = \array_map(fn (array $tool) => Tool::make($tool['name'], $tool['description'])
            ->setInputs($tool['inputs'])
            ->setCallId($tool['callId'] ?? null), $message['tools']);

        $item = new ToolCallMessage($message['content'], $tools);

        $this->deserializeMeta($message, $item);

        return $item;
    }

    protected function deserializeToolCallResult(array $message): ToolCallResultMessage
    {
        $tools = \array_map(fn (array $tool) => Tool::make($tool['name'], $tool['description'])
            ->setInputs($tool['inputs'])
            ->setCallId($tool['callId'])
            ->setResult($tool['result']), $message['tools']);

        return new ToolCallResultMessage($tools);
    }

    /**
     * @param array $message
     * @param Message $item
     * @return void
     */
    protected function deserializeMeta(array $message, Message $item): void
    {
        foreach ($message as $key => $value) {
            if ($key === 'role' || $key === 'content') {
                continue;
            }
            if ($key === 'usage') {
                $item->setUsage(
                    new Usage($message['usage']['input_tokens'], $message['usage']['output_tokens'])
                );
                continue;
            }
            if ($key === 'attachments') {
                foreach ($message['attachments'] as $attachment) {
                    switch (AttachmentType::from($attachment['type'])) {
                        case AttachmentType::IMAGE:
                            $item->addAttachment(
                                new Image(
                                    $attachment['content'],
                                    AttachmentContentType::from($attachment['content_type']),
                                    $attachment['media_type'] ?? null
                                )
                            );
                            break;
                        case AttachmentType::DOCUMENT:
                            $item->addAttachment(
                                new Document(
                                    $attachment['content'],
                                    AttachmentContentType::from($attachment['content_type']),
                                    $attachment['media_type'] ?? null
                                )
                            );
                            break;
                    }

                }
                continue;
            }
            $item->addMetadata($key, $value);
        }
    }
}
