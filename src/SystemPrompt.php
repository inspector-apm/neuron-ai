<?php

namespace NeuronAI;

class SystemPrompt implements \Stringable
{
    public function __construct(
        public array $background,
        public array $steps = [],
        public array $output = [],
        public array $toolsUsage = []
    ) {
    }

    public function __toString(): string
    {
        $prompt = "# IDENTITY and PURPOSE" . PHP_EOL . implode(PHP_EOL, $this->background);

        if (!empty($this->steps)) {
            $prompt .= PHP_EOL . PHP_EOL . "# INTERNAL ASSISTANT STEPS" . PHP_EOL . implode(PHP_EOL, $this->steps);
        }

        if (!empty($this->output)) {
            $prompt .= PHP_EOL . PHP_EOL . "# OUTPUT INSTRUCTIONS" . PHP_EOL
                . " - " . implode(PHP_EOL . " - ", [
                    ...$this->output,
                    "Always respond using the proper JSON schema.",
                    "Always use the available additional information and context to enhance the response."
                ]);
        }

        if (!empty($this->toolsUsage)) {
            $prompt .= PHP_EOL . PHP_EOL . "# CRITICAL TOOLS USAGE RULES" . PHP_EOL
                . " - " . implode(PHP_EOL . " - ", [
                    ...$this->toolsUsage,
                    "When using tools, provide ONLY valid JSON parameters",
                    "Do NOT add any comments, explanations, or text outside the tool call.",
                    "The JSON must be pure, perfectly valid and deserializable."
                ]);
        }

        return $prompt;
    }
}
