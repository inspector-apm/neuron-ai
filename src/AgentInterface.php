<?php

namespace NeuronAI;

use NeuronAI\Chat\History\AbstractChatHistory;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\ToolInterface;

interface AgentInterface
{
    public function provider(): AIProviderInterface;

    public function setProvider(AIProviderInterface $provider): AgentInterface;

    public function instructions(): ?string;

    public function setInstructions(?string $instructions): AgentInterface;

    public function tools(): array;

    public function addTool(ToolInterface $tool): AgentInterface;

    public function resolveChatHistory(): AbstractChatHistory;

    public function withChatHistory(AbstractChatHistory $chatHistory): AgentInterface;

    public function observe(\Inspector\Inspector $inspector): AgentInterface;

    public function chat(Message|array $messages): Message;
}
