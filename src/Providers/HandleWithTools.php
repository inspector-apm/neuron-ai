<?php

namespace NeuronAI\Providers;

use NeuronAI\Tools\ToolInterface;

trait HandleWithTools
{
    /**
     * https://docs.anthropic.com/en/docs/build-with-claude/tool-use/overview
     *
     * @var array<ToolInterface>
     */
    protected array $tools = [];

    public function setTools(array $tools): self
    {
        $this->tools = $tools;
        return $this;
    }

    public function findTool($name): ?ToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }

        return null;
    }
}
