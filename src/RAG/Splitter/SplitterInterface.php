<?php

namespace NeuronAI\RAG\Splitter;

use NeuronAI\RAG\Document;

interface SplitterInterface
{
    /**
     * @param  Document  $document
     * @return array<Document>
     */
    public function splitDocument(Document $document): array;

    /**
     * @param  array<Document>  $documents
     * @return array<Document>
     */
    public function splitDocuments(array $documents): array;
}
