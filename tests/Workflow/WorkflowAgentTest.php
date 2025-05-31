<?php

declare(strict_types=1);

namespace NeuronAI\Tests\Workflow;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use NeuronAI\Agent;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Workflow\StateGraph;
use NeuronAI\Workflow\WorkflowAgent;
use PHPUnit\Framework\TestCase;
use NeuronAI\Observability\LogObserver;

class WorkflowAgentTest extends TestCase
{
    /**
     * This test checks if the evaluation of a state graph properly works.
     */
    public function test_agent_call_order(): void
    {
        $graph = (new StateGraph())
            ->addNode('a', new TestAgent('a'))
            ->addNode('b', new TestAgent('b'))
            ->addNode('c', new TestAgent('c'))
            ->addEdge(StateGraph::START_NODE, 'a')
            ->addEdge('a', 'b')
            ->addEdge('a', 'c')
            ->addEdge('c', 'b')
            ->addEdge('b', StateGraph::END_NODE);

        $handler = new TestHandler();

        $agent = WorkflowAgent::make($graph);
        $agent->observe(
            new LogObserver(new Logger('my_logger', [$handler])),
            'test',
        );

        $reply = $agent->chat(new UserMessage('hello'));

        $this->assertInstanceOf(Message::class, $reply);
        $this->assertEquals(MessageRole::ASSISTANT->value, $reply->getRole());
        $this->assertEquals('b', $reply->getContent());

        $records = $handler->getRecords();

        $this->assertCount(3, $records);
        $this->assertEquals('Evaluate a', $records[0]['context']['data']);
        $this->assertEquals('Evaluate c', $records[1]['context']['data']);
        $this->assertEquals('Evaluate b', $records[2]['context']['data']);
    }

    /**
     * This test checks if chaining two agents works.
     *
     * The first agent has a tool to retrive a (hard-coded) timezone for a location.
     * The second agent has a tool to retrieve the date and time for a timezone.
     *
     * When asking "What time is it in Paris/France" the two tools must have been called.
     */
    public function test_with_real_agents(): void
    {
        // TODO: remove this line if you have the requirements installed
        $this->markTestSkipped('This test requires an Ollama server with the qwen2.5:3b model installed');

        /**
         * If the test is skipped, phpstan will complain...
         * @phpstan-ignore deadCode.unreachable
         */
        $handler = new TestHandler();

        $provider = new Ollama(
            url: 'http://localhost:11434/api', // TODO: adapt to match your Ollama server URL
            model: 'qwen2.5:3b',
        );

        $geocodeTool = Tool::make('get_timezone', 'Get the timezone of a location.')
            ->addProperty(
                new ToolProperty(
                    name: 'location',
                    type: 'string',
                    description: 'The location to get the timezone of.',
                    required: true,
                )
            )
            ->setCallable(fn (string $location) => 'CET' /* Result hard-coded for the tests */);

        $timeTool = Tool::make('get_time_and_date', 'Get the current time and date.')
            ->addProperty(
                new ToolProperty(
                    name: 'timezone',
                    type: 'string',
                    description: 'The timezone.',
                    required: true,
                )
            )
            ->setCallable(
                fn (string $timezone) => (new \DateTimeImmutable("now", new \DateTimeZone($timezone)))->format('Y-m-d H:i:s')
            );


        $agent1 = Agent::make()
            ->withProvider($provider)
            ->withInstructions('You are an AI agent specialized in retrieving the timezone for a location')
            ->addTool($geocodeTool);

        $agent2 = Agent::make()
            ->withProvider($provider)
            ->withInstructions('You are an AI agent specialized in giving the current time for a given timezone. Always use the tool get_time_and_date.')
            ->addTool($timeTool);

        $graph = (new StateGraph())
            ->addNode('a', $agent1)
            ->addNode('b', $agent2)
            ->addEdge(StateGraph::START_NODE, 'a')
            ->addEdge('a', 'b')
            ->addEdge('b', StateGraph::END_NODE);

        $reply = WorkflowAgent::make($graph)
            ->observe(new LogObserver(new Logger('my_logger', [$handler])))
            ->chat(new UserMessage('What time is it in Paris/France'));


        $records = $handler->getRecords();

        // TODO: uncomment to see all the events.
        // foreach ($records as $record) {
        //     echo $record['message'] . ' - ' . json_encode($record['context']) . PHP_EOL . PHP_EOL;
        // }

        $list = array_filter($records, fn ($record) => $record->message === 'tool-called');

        $this->assertCount(2, $list);
    }
}
