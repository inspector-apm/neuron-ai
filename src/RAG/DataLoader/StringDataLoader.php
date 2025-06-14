<?php

namespace NeuronAI\RAG\DataLoader;

class StringDataLoader extends AbstractDataLoader
{
    public function __construct(protected string $content)
    {
    }

    public function getDocuments(): array
    {
        return DocumentSplitter::splitDocument(
            new $this->documentModel($this->content),
            $this->maxLength,
            $this->separator,
            $this->wordOverlap
        );
    }
}
