<?php

namespace NeuronAI\Tests\PreProcessor;

use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\RAG\PreProcessor\PreProcessorInterface;
use NeuronAI\RAG\PreProcessor\QueryTransformationPreProcessor;
use NeuronAI\SystemPrompt;
use PHPUnit\Framework\TestCase;

class QueryTransformationPreProcessorTest extends TestCase
{
    public function test_instance()
    {
        $processor = new QueryTransformationPreProcessor(
            provider: new Anthropic(
                'key',
                'model'
            )
        );

        $this->assertInstanceOf(QueryTransformationPreProcessor::class, $processor);
        $this->assertInstanceOf(PreProcessorInterface::class, $processor);
    }

    public function test_override_instructions_constructor()
    {
        $prompt = new SystemPrompt(
            background: ['background'],
            steps: ['steps'],
            output: ['output'],
        );

        $processor = new QueryTransformationPreProcessor(
            new Anthropic(
                'key',
                'model'
            ),
            customPrompt: $prompt
        );

        $this->assertEquals($prompt, $processor->getSystemPrompt());
    }

    public function test_override_instructions_setter()
    {
        $prompt = new SystemPrompt(
            background: ['background'],
            steps: ['steps'],
            output: ['output'],
        );

        $processor = new QueryTransformationPreProcessor(
            new Anthropic(
                'key',
                'model'
            )
        );

        $processor->setCustomPrompt($prompt);

        $this->assertEquals($prompt, $processor->getSystemPrompt());
    }

}
