<?php

namespace NeuronAI;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\MessageSaved;
use NeuronAI\Observability\Events\MessageSaving;
use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Exceptions\MissingCallbackParameter;
use NeuronAI\Exceptions\ToolCallableNotSet;

trait HandleChat
{
    /**
     * Execute the chat.
     *
     * @param Message|array $messages
     * @return Message
     * @throws MissingCallbackParameter
     * @throws ToolCallableNotSet
     */
    public function chat(Message|array $messages): Message
    {
        try {
            $this->notify('chat-start');

            $this->fillChatHistory($messages);

            $this->notify(
                'inference-start',
                new InferenceStart($this->resolveChatHistory()->getLastMessage())
            );

            $response = $this->resolveProvider()
                ->systemPrompt($this->instructions())
                ->setTools($this->tools())
                ->chat(
                    $this->resolveChatHistory()->getMessages()
                );

            $this->notify(
                'inference-stop',
                new InferenceStop($this->resolveChatHistory()->getLastMessage(), $response)
            );

            if ($response instanceof ToolCallMessage) {
                $toolCallResult = $this->executeTools($response);
                $response = $this->chat([$response, $toolCallResult]);
            } else {
                $this->notify('message-saving', new MessageSaving($response));
                $this->resolveChatHistory()->addMessage($response);
                $this->notify('message-saved', new MessageSaved($response));
            }

            $this->notify('chat-stop');
            return $response;
        } catch (\Throwable $exception) {
            $this->notify('error', new AgentError($exception));
            throw $exception;
        }
    }
}
