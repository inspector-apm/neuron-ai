<?php

namespace NeuronAI\Tests;

use NeuronAI\Properties\BasicProperty;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolInterface;
use PHPUnit\Framework\TestCase;

class ToolTest extends TestCase
{
    public function test_tool_instance()
    {
        $tool = new Tool('example', 'example');
        $this->assertInstanceOf(ToolInterface::class, $tool);

        $tool->setInputs(null);
        $this->assertEquals([], $tool->getInputs());
    }

    public function test_required_properties()
    {
        $tool = Tool::make('test', 'Test tool')
            ->addProperty(
                new BasicProperty('name', 'string', 'User name', true)
            )
            ->addProperty(
                new BasicProperty('surname', 'string', 'User surname', false)
            )
            ->addProperty(
                new BasicProperty('age', 'integer', 'User age', true)
            )
            ->setCallable(function (): void {});

        $properties = $tool->getRequiredProperties();
        $this->assertEquals(['name', 'age'], $properties);
    }

    public function test_tool_return_value()
    {
        $tool = Tool::make('test', 'Test tool');

        $tool->setCallable(fn () => 'test')->execute();
        $this->assertEquals('test', $tool->getResult());

        $tool->setCallable(fn () => ['test'])->execute();
        $this->assertEquals('["test"]', $tool->getResult());

        $tool->setCallable(fn () => ['foo' => 'bar'])->execute();
        $this->assertEquals('{"foo":"bar"}', $tool->getResult());

        $tool->setCallable(fn () => new class () {
            public function __toString(): string
            {
                return 'test';
            }
        })->execute();
        $this->assertEquals('test', $tool->getResult());
    }

    public function test_invalid_return_type()
    {
        $tool = Tool::make('test', 'Test tool');

        $this->expectException(\TypeError::class);

        $tool->setCallable(fn () => new class () {})->execute();
    }
}
