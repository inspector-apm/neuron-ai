<?php

namespace NeuronAI\RAG;

abstract class AbstractDocumentModel implements DocumentModelInterface
{
    public string|int $id;

    public array $embedding = [];

    public string $sourceType = 'manual';

    public string $sourceName = 'manual';

    public float $score = 0;

    public function __construct(
        public string $content = '',
    ) {
        $this->id = \uniqid();
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): DocumentModelInterface
    {
        $this->score = $score;
        return $this;
    }

    public function getCustomFields(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        $props = [];
        $exclude = ['id', 'content', 'embedding', 'sourceType', 'sourceName', 'score'];
        foreach ($properties as $property) {
            if (!\in_array($property->getName(), $exclude)) {
                $props[$property->getName()] = $property->getValue($this);
            }
        }

        return $props;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'content' => $this->getContent(),
            'embedding' => $this->getEmbedding(),
            'sourceType' => $this->getSourceType(),
            'sourceName' => $this->getSourceName(),
            'score' => $this->getScore(),
            ...$this->getCustomFields(),
        ];
    }
}
