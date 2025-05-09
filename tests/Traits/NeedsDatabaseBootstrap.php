<?php

namespace NeuronAI\Tests\Traits;

trait NeedsDatabaseBootstrap
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $scriptPath = __DIR__ . '/../scripts/setup-test-db.php';

        if (!file_exists($scriptPath)) {
            throw new \RuntimeException("Bootstrap script not found at $scriptPath");
        }

        require_once $scriptPath;
    }
}
