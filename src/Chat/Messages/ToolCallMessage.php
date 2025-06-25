<?php

declare(strict_types=1);

namespace NeuronAI\Chat\Messages;

use NeuronAI\Tools\ToolInterface;

class ToolCallMessage extends AssistantMessage
{
    /**
     * @param array<ToolInterface> $tools
     */
    public function __construct(
        protected array|string|int|float|null $content,
        protected array $tools
    ) {
        parent::__construct($this->content);
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
                'type' => 'tool_call',
                'tools' => \array_map(fn (ToolInterface $tool): array => $tool->jsonSerialize(), $this->tools)
            ]
        );
    }
}
