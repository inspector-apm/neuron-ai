<?php

declare(strict_types=1);

namespace NeuronAI\Tests\Workflow;

use NeuronAI\Agent;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;

class TestAgent extends Agent
{
    public function __construct(
        private readonly string $data = ''
    ) {
    }

    public function chat(Message|array $messages): Message
    {
        $this->notify('test', "Evaluate {$this->data}");

        return new Message(MessageRole::ASSISTANT, $this->data);
    }

    // public function stream(Message|array $messages): \Generator
    // {
    //     yield $this->data;
    // }

    // public function structured(Message|array $messages, ?string $class = null, int $maxRetries = 1): mixed
    // {
    //     return [];
    // }
}
