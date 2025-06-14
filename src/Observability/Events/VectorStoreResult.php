<?php

namespace NeuronAI\Observability\Events;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\RAG\DocumentModelInterface;

class VectorStoreResult
{
    /**
     * @param DocumentModelInterface[] $documents
     */
    public function __construct(
        public Message $question,
        public array $documents,
    ) {
    }
}
