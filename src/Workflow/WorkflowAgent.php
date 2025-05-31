<?php

declare(strict_types=1);

namespace NeuronAI\Workflow;

use NeuronAI\Agent;
use NeuronAI\AgentInterface;
use NeuronAI\Chat\Messages\Message;

class WorkflowAgent extends Agent
{
    /** @var string[] */
    private array $executionList;

    /** @var array{event:string,observer:\SplObserver}[] */
    private array $observers = [];

    /** @var array<string,Message[]> */
    private array $replies = [];

    public function __construct(
        private readonly StateGraph $graph,
    ) {
        $this->executionList = $graph->compile();
    }

    public function chat(Message|array $messages): Message
    {
        $lastReply = null;

        $this->notify('pipeline-start');

        foreach ($this->graph->getNodeNames() as $node) {
            $this->replies[$node] = [];
        }

        foreach ($this->executionList as $node) {
            $agent = $this->graph->getNode($node);
            $input = $this->getPayload($node, $messages);

            $this->attachObservers($agent);

            $lastReply = $agent->chat($input);
            $this->replies[$node] = [$lastReply];
        }

        $this->notify('pipeline-end');

        return $lastReply;
    }

    public function observe(\SplObserver $observer, string $event = "*"): Agent
    {
        $this->observers[] = [
            'event' => $event,
            'observer' => $observer,
        ];

        return parent::observe($observer, $event);
    }

    private function getPayload(string $node, Message|array $messages): array
    {
        // Always add the original query
        $input = is_array($messages) ? $messages : [$messages];

        // Add the replies of all the predecessors
        foreach ($this->graph->getPredecessors($node) as $predecessor) {
            $input = array_merge($input, $this->replies[$predecessor]);
        }

        return $input;
    }

    private function attachObservers(AgentInterface $agent): void
    {
        foreach ($this->observers as $observer) {
            $agent->observe($observer['observer'], $observer['event']);
        }
    }
}
