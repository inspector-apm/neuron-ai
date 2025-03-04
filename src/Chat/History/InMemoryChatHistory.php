<?php

namespace NeuronAI\Chat\History;

use NeuronAI\Chat\Messages\Message;

class InMemoryChatHistory extends AbstractChatHistory
{
    public function __construct(protected int $contextWindow = 50000) {}

    protected array $history = [];

    public function addMessage(Message $message): self
    {
        $this->history[] = $message;

        $freeMemory = $this->contextWindow - $this->calculateTotalUsage();

        if ($freeMemory < 0) {
            $this->truncate();
        }

        return $this;
    }

    public function getMessages(): array
    {
        return $this->history;
    }

    public function clear(): self
    {
        $this->history = [];
        return $this;
    }

    public function count(): int
    {
        return count($this->history);
    }

    public function truncate(): self
    {
        do {
            \array_pop($this->history);
        } while ($this->contextWindow - $this->calculateTotalUsage() < 0);

        return $this;
    }
}
