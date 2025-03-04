<?php

namespace NeuronAI\Providers;

use NeuronAI\Chat\Messages\Message;

interface AIProviderInterface
{
    /**
     * Send predefined instruction to the LLM.
     *
     * @param ?string $prompt
     * @return AIProviderInterface
     */
    public function systemPrompt(?string $prompt): self;

    /**
     * Set the tools to be exposed to the LLM.
     *
     * @param array $tools
     * @return AIProviderInterface
     */
    public function setTools(array $tools): self;

    /**
     * Send a prompt to the AI agent.
     *
     * @param Message|array<Message> $messages
     * @return Message
     */
    public function chat(Message|array $messages): Message;

    //public function structured(array|string $messages): Message;

    //public function stream(array|string $messages): Message;

    /**
     * The context window limitation of the LLM.
     *
     * @return ?int
     */
    //public function contextWindow(): ?int;

    /**
     * The maximum number of tokens to generate before stopping.
     *
     * @return ?int
     */
    //public function maxCompletionTokens(): ?int;
}
