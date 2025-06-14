<?php

namespace NeuronAI\RAG\DataLoader;

use NeuronAI\RAG\DocumentModelInterface;

interface DataLoaderInterface
{
    /**
     * @return DocumentModelInterface[]
     */
    public function getDocuments(): array;
}
