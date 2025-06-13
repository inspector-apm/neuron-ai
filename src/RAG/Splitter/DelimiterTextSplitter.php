<?php

namespace NeuronAI\RAG\Splitter;

use NeuronAI\RAG\Document;

class DelimiterTextSplitter implements SplitterInterface
{
    private int $maxLength;
    private string $separator;
    private int $wordOverlap;

    public function __construct(int $maxLength = 1000, string $separator = ' ', int $wordOverlap = 0)
    {
        $this->maxLength = $maxLength;
        $this->separator = $separator;
        $this->wordOverlap = $wordOverlap;
    }

    /**
     * @return array<Document>
     */
    public function splitDocument(Document $document): array
    {
        $text = $document->content;

        if (empty($text)) {
            return [];
        }

        if (\strlen($text) <= $this->maxLength) {
            if (empty($document->hash)) {
                $document->hash = \hash('sha256', $text);
            }

            if (empty($document->id)) {
                $document->id = \uniqid();
            }
            return [$document];
        }

        $parts = \explode($this->separator, $text);

        $chunks = $this->createChunksWithOverlap($parts);

        $split = [];
        $chunkNumber = 0;
        foreach ($chunks as $chunk) {
            $newDocument = new Document($chunk);
            $newDocument->hash = \hash('sha256', $chunk);
            $newDocument->id = \uniqid();
            $newDocument->sourceType = $document->sourceType;
            $newDocument->sourceName = $document->sourceName;
            $newDocument->chunkNumber = $chunkNumber;
            $chunkNumber++;
            $split[] = $newDocument;
        }

        return $split;
    }

    /**
     * @param  array<Document>  $documents
     * @return array<Document>
     */
    public function splitDocuments(array $documents): array
    {
        $split = [];

        foreach ($documents as $document) {
            $split = \array_merge($split, $this->splitDocument($document));
        }

        return $split;
    }

    /**
     * @param  array<string>  $words
     * @return array<string>
     */
    private function createChunksWithOverlap(array $words): array
    {
        $chunks = [];
        $currentChunk = [];
        $currentChunkLength = 0;
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            if ($currentChunkLength + \strlen($this->separator.$word) <= $this->maxLength || $currentChunk === []) {
                $currentChunk[] = $word;
                $currentChunkLength = $this->calculateChunkLength($currentChunk, $this->separator);
            } else {
                // Add the chunk with overlap
                $chunks[] = \implode($this->separator, $currentChunk);

                // Calculate overlap words
                $calculatedOverlap = \min($this->wordOverlap, \count($currentChunk) - 1);
                $overlapWords = $calculatedOverlap > 0 ? \array_slice($currentChunk, -$calculatedOverlap) : [];

                // Start a new chunk with overlap words
                $currentChunk = [...$overlapWords, $word];
                $currentChunk[0] = \trim($currentChunk[0]);
                $currentChunkLength = $this->calculateChunkLength($currentChunk, $this->separator);
            }
        }

        if ($currentChunk !== []) {
            $chunks[] = \implode($this->separator, $currentChunk);
        }

        return $chunks;
    }

    /**
     * @param  array<string>  $currentChunk
     */
    private function calculateChunkLength(array $currentChunk, string $separator): int
    {
        return \array_sum(\array_map('strlen', $currentChunk)) + \count($currentChunk) * \strlen($this->separator) - 1;
    }
}
