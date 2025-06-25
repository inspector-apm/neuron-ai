<?php

declare(strict_types=1);

namespace NeuronAI\Observability;

use Inspector\Configuration;
use Inspector\Inspector;
use Inspector\Models\Segment;
use NeuronAI\Agent;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\RAG\RAG;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\Toolkits\ToolkitInterface;
use NeuronAI\Tools\ToolPropertyInterface;

/**
 * Trace your AI agent execution flow to detect errors and performance bottlenecks in real-time.
 *
 * Getting started with observability:
 * https://docs.neuron-ai.dev/advanced/observability
 */
class AgentMonitoring implements \SplObserver
{
    use HandleToolEvents;
    use HandleRagEvents;
    use HandleInferenceEvents;
    use HandleStructuredEvents;
    use HandleWorkflowEvents;

    public const SEGMENT_TYPE = 'neuron';
    public const SEGMENT_COLOR = '#FF800C';

    /**
     * @var array<string, Segment>
     */
    protected array $segments = [];


    /**
     * @var array<string, string>
     */
    protected array $methodsMap = [
        'error' => 'reportError',
        'chat-start' => 'start',
        'chat-stop' => 'stop',
        'stream-start' => 'start',
        'stream-stop' => 'stop',
        'rag-start' => 'start',
        'rag-stop' => 'stop',
        'structured-start' => 'start',
        'structured-stop' => 'stop',
        'message-saving' => 'messageSaving',
        'message-saved' => 'messageSaved',
        'tools-bootstrapping' => 'toolsBootstrapping',
        'tools-bootstrapped' => 'toolsBootstrapped',
        'inference-start' => 'inferenceStart',
        'inference-stop' => 'inferenceStop',
        'tool-calling' => 'toolCalling',
        'tool-called' => 'toolCalled',
        'structured-extracting' => 'extracting',
        'structured-extracted' => 'extracted',
        'structured-deserializing' => 'deserializing',
        'structured-deserialized' => 'deserialized',
        'structured-validating' => 'validating',
        'structured-validated' => 'validated',
        'rag-vectorstore-searching' => 'vectorStoreSearching',
        'rag-vectorstore-result' => 'vectorStoreResult',
        'rag-preprocessing' => 'preProcessing',
        'rag-preprocessed' => 'preProcessed',
        'rag-postprocessing' => 'postProcessing',
        'rag-postprocessed' => 'postProcessed',
        'workflow-start' => 'workflowStart',
        'workflow-end' => 'workflowEnd',
        'workflow-node-start' => 'workflowNodeStart',
        'workflow-node-end' => 'workflowNodeEnd',
    ];

    protected static ?AgentMonitoring $instance = null;

    /**
     * @param Inspector $inspector The monitoring instance
     * @param bool $catch Report internal agent errors
     */
    public function __construct(
        protected Inspector $inspector,
        protected bool $catch = true
    ) {
    }


    public static function instance(): AgentMonitoring
    {
        $configuration = new Configuration($_ENV['INSPECTOR_INGESTION_KEY']);
        $configuration->setTransport($_ENV['INSPECTOR_TRANSPORT'] ?? 'async');

        if (isset($_ENV['INSPECTOR_SPLIT_MONITORING'])) {
            return new self(new Inspector($configuration));
        }

        if (self::$instance === null) {
            self::$instance = new self(new Inspector($configuration));
        }
        return self::$instance;
    }

    public function update(\SplSubject $subject, ?string $event = null, mixed $data = null): void
    {
        if (!\is_null($event) && \array_key_exists($event, $this->methodsMap)) {
            $method = $this->methodsMap[$event];
            $this->$method($subject, $event, $data);
        }
    }

    public function reportError(\SplSubject $subject, string $event, AgentError $data): void
    {
        if ($this->catch) {
            $this->inspector->reportException($data->exception, !$data->unhandled);

            if ($data->unhandled) {
                $this->inspector->transaction()->setResult('error');
            }
        }
    }

    public function start(Agent $agent, string $event, mixed $data = null): void
    {
        if (!$this->inspector->isRecording()) {
            return;
        }

        $method = $this->getPrefix($event);
        $class = $agent::class;

        if ($this->inspector->needTransaction()) {
            $this->inspector->startTransaction($class.'::'.$method)->setType('ai-agent');
        } elseif ($this->inspector->canAddSegments() && !$agent instanceof RAG) { // do not add "chat" segments on RAG
            $key = $class.$method;

            if (\array_key_exists($key, $this->segments)) {
                $key .= '-'.\uniqid();
            }

            $this->segments[$key] = $this->inspector->startSegment(self::SEGMENT_TYPE.'-'.$method, "{$class}::{$method}()")
                ->setColor(self::SEGMENT_COLOR);
        }
    }

    public function stop(Agent $agent, string $event, mixed $data = null): void
    {
        $method = $this->getPrefix($event);
        $class = $agent::class;

        if (\array_key_exists($class.$method, $this->segments)) {
            // End the last segment for the given method and agent class
            foreach (\array_reverse($this->segments, true) as $key => $value) {
                if ($key === $class.$method) {
                    $value->setContext($this->getContext($agent));
                    $value->end();
                    unset($this->segments[$key]);
                    break;
                }
            }
        } elseif ($this->inspector->canAddSegments()) {
            $transaction = $this->inspector->transaction()->setResult('success');
            $transaction->setContext($this->getContext($agent));
        }
    }

    public function getPrefix(string $event): string
    {
        return \explode('-', $event)[0];
    }

    protected function getContext(Agent $agent): array
    {
        $mapTool = fn (ToolInterface $tool) => [
            $tool->getName() => [
                'description' => $tool->getDescription(),
                'properties' => \array_map(
                    fn (ToolPropertyInterface $property) => $property->jsonSerialize(),
                    $tool->getProperties()
                )
            ]
        ];

        return [
            'Agent' => [
                'provider' => $agent->resolveProvider()::class,
                'instructions' => $agent->resolveInstructions(),
            ],
            'Tools' => \array_map(
                fn (ToolInterface|ToolkitInterface $tool) => $tool instanceof ToolInterface
                    ? $mapTool($tool)
                    : [$tool::class => \array_map($mapTool, $tool->tools())],
                $agent->getTools()
            ),
            //'Messages' => $agent->resolveChatHistory()->getMessages(),
        ];
    }

    public function getMessageId(Message $message): string
    {
        $content = $message->getContent();

        if (!\is_string($content)) {
            $content = \json_encode($content, \JSON_UNESCAPED_UNICODE);
        }

        return \md5($content.$message->getRole());
    }

    protected function getBaseClassName(string $class): string
    {
        return \substr(\strrchr($class, '\\'), 1);
    }
}
