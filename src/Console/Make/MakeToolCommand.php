<?php

declare(strict_types=1);

namespace NeuronAI\Console\Make;

class MakeToolCommand extends MakeCommand
{
    public function __construct()
    {
        parent::__construct('Tool');
    }

    protected function getStubContent(string $namespace, string $className): string
    {
        $stubPath = __DIR__ . '/Stubs/tool.stub';
        $stub = \file_get_contents($stubPath);

        if ($stub === false) {
            throw new \RuntimeException("Failed to read stub file: {$stubPath}");
        }

        return \str_replace(
            ['[namespace]', '[classname]'],
            [$namespace, $className],
            $stub
        );
    }
}
