<?php declare(strict_types=1);

namespace NeuronAI\Workflow;

use NeuronAI\AgentInterface;
use NeuronAI\Exceptions\StateGraphError;

class StateGraph
{
    public const START_NODE = '__start__';

    public const END_NODE = '__end__';

    /** @var array<string, AgentInterface|null> */
    private array $nodes = [];

    /** @var array<string, string[]> */
    private array $edges = [];

    public function __construct()
    {
        $this->nodes[self::START_NODE] = null;
        $this->nodes[self::END_NODE] = null;
        $this->edges[self::START_NODE] = [];
        $this->edges[self::END_NODE] = [];
    }

    public function addNode(string $name, AgentInterface $node): self
    {
        if ($this->nodeExists($name)) {
            throw new StateGraphError("Node already exists: $name");
        }

        $this->nodes[$name] = $node;
        $this->edges[$name] = [];
        return $this;
    }

    public function addEdge(string $from, string $to): self
    {
        if ($to === self::START_NODE) {
            throw new StateGraphError('Cannot add edges ending to the START node');
        }

        if ($from === self::END_NODE) {
            throw new StateGraphError('Cannot add edges starting from the END node');
        }

        if ($from === $to) {
            throw new StateGraphError('Cannot add edges from one node to itself');
        }

        $this->assertNodeExists($from);
        $this->assertNodeExists($to);

        if (!in_array($to, $this->edges[$from])) {
            $this->edges[$from][] = $to;
        }

        return $this;
    }

    /** @return string[] */
    public function getNodeNames(): array
    {
        return array_keys($this->nodes);
    }

    /**
     * Get the agent attached to a node.
     */
    public function getNode(string $name): AgentInterface
    {
        $this->assertNodeExists($name);

        if ($name === self::START_NODE) {
            throw new StateGraphError('START node is not attached to an agent');
        }

        if ($name === self::END_NODE) {
            throw new StateGraphError('END node is not attached to an agent');
        }

        /** @var AgentInterface $node */
        $node = $this->nodes[$name];

        return $node;
    }

    /** @return string[] */
    public function getSuccessors(string $node): array
    {
        $this->assertNodeExists($node);
        return $this->edges[$node];
    }

    /** @return string[] */
    public function getPredecessors(string $node): array
    {
        $this->assertNodeExists($node);

        $predecessors = [];

        foreach ($this->edges as $current => $successors) {
            if (in_array($node, $successors)) {
                $predecessors[] = $current;
            }
        }

        return $predecessors;
    }

    public function nodeExists(string $name): bool
    {
        return array_key_exists($name, $this->nodes);
    }

    public function isStartConnectedToEnd(): bool
    {
        return $this->nodeIsConnectedTo(self::START_NODE, self::END_NODE);
    }

    /**
     * Get a list of node names to evaluate to get from the start node to the end node.
     *
     * @return string[]
     */
    public function compile(): array
    {
        if (!$this->isStartConnectedToEnd()) {
            throw new StateGraphError('Start node is not connected to end node');
        }

        if ($this->isCyclic()) {
            throw new StateGraphError('The graph contains cycles');
        }

        /** @var array<string> */
        $executionList = [];

        /** @var array<string, bool> */
        $evaluated = [];

        foreach (array_keys($this->nodes) as $node) {
            $evaluated[$node] = false;
        }

        $q = new \SplQueue();
        $q->enqueue(self::START_NODE);
        $evaluated[self::START_NODE] = true;

        while (!$q->isEmpty()) {
            /** @var string */
            $node = $q->dequeue();

            if ($node !== self::END_NODE) {
                if ($this->pathExists($node, self::END_NODE, [$this, 'getSuccessors'])) {
                    $this->evaluateNode($node, $evaluated, $executionList);

                    foreach ($this->getSuccessors($node) as $successor) {
                        if (!$evaluated[$successor]) {
                            $q->enqueue($successor);
                        }
                    }
                }
            }
        }

        return $executionList;
    }

    /**
     * Export the graph in Graphwiz format
     * @see https://graphviz.org/doc/info/lang.html
     */
    public function toDot(): string
    {
        return $this->exportToGraph(
            static function (string $from, array $to, callable $normalize): string {
                $destination = count($to) === 1
                    ? $normalize($to[0])
                    : sprintf('{%s}', implode(',', array_map(fn($node) => $normalize($node), $to)));

                return sprintf("  %s -> %s", $normalize($from), $destination);
            },
            static fn (array $edges): string => sprintf("digraph G {\n%s\n}", implode(PHP_EOL, $edges))
        );
    }

    /**
     * Export the graph in Mermaid format.
     * @see https://mermaid.js.org
     */
    public function toMermaid(): string
    {
        return $this->exportToGraph(
            static function (string $from, array $to, callable $normalize): string {
                $destination = count($to) === 1
                    ? $normalize($to[0])
                    : implode(' & ', array_map(fn($node) => $normalize($node), $to));

                return sprintf("  %s --> %s;", $normalize($from), $destination);
            },
            static fn (array $edges): string => sprintf("graph TD;\n%s\n", implode(PHP_EOL, $edges))
        );
    }

    /**
     * Internal function used to serialize the graph to Dot or Mermaid format.
     * @param callable(string,string[],callable(string): string): string $serializeEdges
     * @param callable(string[]): string $serializeGraph
     * @return string
     */
    private function exportToGraph(
        callable $serializeEdges,
        callable $serializeGraph,
    ): string
    {
        $edges = [];

        $normalize = static fn(string $node) =>
            match ($node) {
                self::START_NODE => 'START',
                self::END_NODE => 'END',
                default => $node,
            };

        foreach ($this->edges as $from => $to) {
            if (count($to) === 0) {
                continue;
            }

            $edges[] = $serializeEdges($from, $to, $normalize);
        }

        return $serializeGraph($edges);
    }

    /**
     * Returns true is there is a path from the $from node to the $to node.
     */
    public function nodeIsConnectedTo(string $from, string $to): bool
    {
        return $this->pathExists($from, $to, [$this, 'getSuccessors']);
    }

    /**
     * Returns true if there is a path from the $dependency node to the $node node.
     */
    public function nodeDependsOn(string $node, string $dependency): bool
    {
        return $this->pathExists($node, $dependency, [$this, 'getPredecessors']);
    }

    public function isCyclic(): bool
    {
        $visited = [];
        $stack = [];

        foreach (array_keys($this->nodes) as $node) {
            $visited[$node] = false;
            $stack[$node] = false;
        }

        foreach (array_keys($this->nodes) as $node) {
            if ($visited[$node]) {
                continue;
            }

            if ($this->isCyclicNode($node, $visited, $stack)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Internal utility function used to detect cycles in the graph.
     *
     * @param bool[] $visited
     * @param bool[] $stack
     * @return bool
     */
    private function isCyclicNode(string $node, array &$visited, array &$stack): bool
    {
        if ($stack[$node]) {
            return true;
        }

        if ($visited[$node]) {
            return false;
        }

        $visited[$node] = true;
        $stack[$node] = true;

        foreach ($this->getSuccessors($node) as $successor) {
            if ($this->isCyclicNode($successor, $visited, $stack)) {
                return true;
            }
        }

        $stack[$node] = false;
        return false;
    }

    /**
     * Depth First Traversal of the graph given the $successorFunction to find a node successors.
     * 
     * @param callable(string): string[] $successorFunction
     */
    private function pathExists(string $from, string $to, callable $successorFunction): bool
    {
        $visited = [];
        $queue = new \SplQueue();
        $queue->enqueue($from);

        while (!$queue->isEmpty()) {
            $node = $queue->dequeue();

            if ($node === $to) {
                return true; // Path found
            }

            if (!isset($visited[$node])) {
                $visited[$node] = true;

                foreach ($successorFunction($node) as $successor) {
                    if (!isset($visited[$successor])) {
                        $queue->enqueue($successor);
                    }
                }
            }
        }

        return false; // No path found
    }

    /**
     * Internal utility function used to compile the graph.
     * @param bool[] $evaluated
     * @param string[] $executionList
     */
    private function evaluateNode(string $node, array &$evaluated, array &$executionList): void
    {
        if ($evaluated[$node]) {
            return;
        }

        foreach ($this->getPredecessors($node) as $predecessor) {
            if (!$evaluated[$predecessor]) {
                $this->evaluateNode($predecessor, $evaluated, $executionList);
            }
        }

        $evaluated[$node] = true;
        $executionList[] = $node;
    }

    private function assertNodeExists(string $name): void
    {
        if (!$this->nodeExists($name)) {
            throw new StateGraphError("Invalid node name: $name");
        }
    }
}
