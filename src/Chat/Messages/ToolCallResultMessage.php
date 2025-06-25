<?php

declare(strict_types=1);

namespace NeuronAI\Chat\Messages;

use NeuronAI\Tools\ToolInterface;

class ToolCallResultMessage extends UserMessage
{
    public function __construct(protected array $tools)
    {
        parent::__construct(null);
    }

    /**
     * @return array<ToolInterface>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    public function jsonSerialize(): array
    {
        return \array_merge(
            parent::jsonSerialize(),
            [
                'type' => 'tool_call_result',
                'tools' => \array_map(fn (ToolInterface $tool): array => $tool->jsonSerialize(), $this->tools)
            ]
        );
    }
}
