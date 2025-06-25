<?php

declare(strict_types=1);

namespace NeuronAI\Providers;

use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Tools\ToolInterface;

trait HandleWithTools
{
    /**
     * https://docs.anthropic.com/en/docs/build-with-claude/tool-use/overview
     *
     * @var array<ToolInterface>
     */
    protected array $tools = [];

    public function setTools(array $tools): AIProviderInterface
    {
        $this->tools = $tools;
        return $this;
    }

    public function findTool(string $name): ToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                // We return a copy to allow multiple call to the same tool.
                return clone $tool;
            }
        }

        throw new ProviderException(
            "It seems the model is asking for a non-existing tool: {$name}. You could try writing more verbose tool descriptions and prompts to help the model in the task."
        );
    }
}
