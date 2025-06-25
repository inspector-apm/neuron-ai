<?php

declare(strict_types=1);

namespace NeuronAI\MCP;

interface McpTransportInterface
{
    public function connect(): void;
    public function send(array $data): void;
    public function receive(): array;
    public function disconnect(): void;
}
