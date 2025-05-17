<?php

namespace NeuronAI\Providers\Gemini;

use NeuronAI\Chat\Attachments\Attachment;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolCallResultMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Providers\MessageMapperInterface;
use NeuronAI\Tools\ToolInterface;

class MessageMapper implements MessageMapperInterface
{
    protected array $mapping = [];

    public function map(array $messages): array
    {
        foreach ($messages as $message) {
            match ($message::class) {
                Message::class,
                UserMessage::class,
                AssistantMessage::class => $this->mapMessage($message),
                ToolCallMessage::class => $this->mapToolCall($message),
                ToolCallResultMessage::class => $this->mapToolsResult($message),
                default => throw new ProviderException('Could not map message type '.$message::class),
            };
        }

        return $this->mapping;
    }

    protected function mapMessage(Message $message): void
    {
        $payload = [
            'role' => $message->getRole(),
            'parts' => [
                ['text' => $message->getContent()]
            ],
        ];

        if ($attachments = $message->getAttachments()) {
            foreach ($attachments as $attachment) {
                $payload['parts'][] = $this->mapAttachment($attachment);
            }
        }

        $this->mapping[] = $payload;
    }

    protected function mapAttachment(Attachment $attachment): array
    {
        return match($attachment->contentType) {
            Attachment::TYPE_URL => [
                'file_data' => [
                    'file_uri' => $attachment->content,
                    'mime_type' => $attachment->mediaType,
                ],
            ],
            Attachment::TYPE_BASE64 => [
                'inline_data' => [
                    'data' => $attachment->content,
                    'mime_type' => $attachment->mediaType,
                ]
            ]
        };
    }

    protected function mapToolCall(ToolCallMessage $message): void
    {
        $this->mapping[] = [
            'role' => Message::ROLE_MODEL,
            'parts' => [
                ...\array_map(function (ToolInterface $tool) {
                    return [
                        'functionCall' => [
                            'name' => $tool->getName(),
                            'args' => $tool->getInputs()?:new \stdClass(),
                        ]
                    ];
                }, $message->getTools())
            ]
        ];
    }

    protected function mapToolsResult(ToolCallResultMessage $message): void
    {
        $this->mapping[] = [
            'role' => Message::ROLE_USER,
            'parts' => \array_map(function (ToolInterface $tool) {
                return [
                    'functionResponse' => [
                        'name' => $tool->getName(),
                        'response' => [
                            'name' => $tool->getName(),
                            'content' => $tool->getResult(),
                        ],
                    ],
                ];
            }, $message->getTools()),
        ];
    }
}
