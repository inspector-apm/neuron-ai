<?php

namespace NeuronAI\RAG\PostProcessor;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\RAG\DocumentModelInterface;

class FixedThresholdPostProcessor implements PostProcessorInterface
{
    /**
     * Creates a post-processor that filters documents based on a fixed threshold.
     *
     * @param float $threshold threshold value (documents with scores below this value will be filtered out)
     */
    public function __construct(
        /**
         * The threshold value below which documents will be filtered out.
         */
        protected float $threshold = 0.5
    ) {
    }

    public function process(Message $question, array $documents): array
    {
        return \array_values(\array_filter($documents, fn (DocumentModelInterface $document) => $document->getScore() >= $this->threshold));
    }
}
