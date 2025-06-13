<?php

namespace NeuronAI\RAG\DataLoader;

use NeuronAI\RAG\Document;

class StringDataLoader extends AbstractDataLoader
{
    public function __construct(protected string $content)
    {
        parent::__construct();
    }

    public function getDocuments(): array
    {
        return $this->splitter->splitDocument(new Document($this->content));
    }
}
