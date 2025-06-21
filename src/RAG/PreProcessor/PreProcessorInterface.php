<?php

namespace NeuronAI\RAG\PreProcessor;

use NeuronAI\Chat\Messages\Message;

interface PreProcessorInterface
{
    /**
     * Process and return the question.
     *
     * @param Message $question The question to process.
     * @return Message The processed question.
     */
    public function process(Message $question): Message;
}
