<?php

namespace NeuronAI\RAG\Splitter;

use NeuronAI\RAG\Document;
use InvalidArgumentException;

/**
 * Splits text into sentences, groups into word-based chunks, and applies
 * overlap in terms of words.
 */
class SentenceTextSplitter implements SplitterInterface
{
    private int $maxWords;
    private int $overlapWords;

    /**
     * @param int $maxWords    Maximum number of words per chunk
     * @param int $overlapWords Number of overlapping words between chunks
     */
    public function __construct(int $maxWords = 200, int $overlapWords = 0)
    {
        if ($overlapWords >= $maxWords) {
            throw new InvalidArgumentException('Overlap must be less than maxWords');
        }

        $this->maxWords = $maxWords;
        $this->overlapWords = $overlapWords;
    }

    /**
     * Splits text into word-based chunks, preserving sentence boundaries.
     *
     * @param Document $document
     * @return Document[] Array of Document chunks
     */
    public function splitDocument(Document $document): array
    {
        // Split by paragraphs (2 or more newlines)
        $paragraphs = preg_split('/\n{2,}/', $document->content);
        $chunks = [];
        $currentWords = [];

        foreach ($paragraphs as $paragraph) {
            $sentences = $this->splitSentences($paragraph);

            foreach ($sentences as $sentence) {
                $sentenceWords = $this->tokenizeWords($sentence);

                // If the sentence alone exceeds the limit, split it
                if (count($sentenceWords) > $this->maxWords) {
                    if (!empty($currentWords)) {
                        $chunks[] = implode(' ', $currentWords);
                        $currentWords = [];
                    }
                    $chunks = array_merge($chunks, $this->splitLongSentence($sentenceWords));
                    continue;
                }

                $candidateCount = count($currentWords) + count($sentenceWords);

                if ($candidateCount > $this->maxWords) {
                    if (!empty($currentWords)) {
                        $chunks[] = implode(' ', $currentWords);
                    }
                    $currentWords = $sentenceWords;
                } else {
                    $currentWords = array_merge($currentWords, $sentenceWords);
                }
            }
        }

        if (!empty($currentWords)) {
            $chunks[] = implode(' ', $currentWords);
        }

        // Apply overlap only if necessary
        if ($this->overlapWords > 0) {
            $chunks = $this->applyOverlap($chunks);
        }

        $split = [];
        $chunkNumber = 0;
        foreach ($chunks as $chunk) {
            $newDocument = new Document($chunk);
            $newDocument->hash = hash('sha256', $chunk);
            $newDocument->id = uniqid();
            $newDocument->sourceType = $document->sourceType;
            $newDocument->sourceName = $document->sourceName;
            $newDocument->chunkNumber = $chunkNumber++;
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
        $result = [];
        foreach ($documents as $document) {
            $result = array_merge($result, $this->splitDocument($document));
        }
        return $result;
    }

    /**
     * Robust regex for sentence splitting (handles ., !, ?, …, periods followed by quotes, etc)
     */
    private function splitSentences(string $text): array
    {
        $pattern = '/(?<=[.!?…])\s+(?=(?:[\"\'\""\'\'«»„""]?)[A-ZÀ-Ÿ])/u';
        $sentences = preg_split($pattern, trim($text));
        return array_filter(array_map('trim', $sentences));
    }

    /**
     * Tokenizes text into words (simple whitespace split).
     *
     * @param string $text
     * @return string[] Array of words
     */
    private function tokenizeWords(string $text): array
    {
        return preg_split('/\s+/u', trim($text));
    }

    /**
     * Applies overlap of words between consecutive chunks.
     *
     * @param string[] $chunks
     * @return string[] Array of chunks with overlap applied
     */
    private function applyOverlap(array $chunks): array
    {
        if (empty($chunks)) {
            return [];
        }

        $result = [$chunks[0]]; // First chunk remains unchanged
        $count = count($chunks);

        for ($i = 1; $i < $count; $i++) {
            $prevWords = $this->tokenizeWords($chunks[$i - 1]);
            $curWords = $this->tokenizeWords($chunks[$i]);

            // Get only the words needed for overlap
            $overlap = array_slice($prevWords, -$this->overlapWords);

            // Remove duplicate words at the beginning of current chunk
            $curWords = array_slice($curWords, $this->overlapWords);

            $merged = array_merge($overlap, $curWords);
            $result[] = implode(' ', $merged);
        }

        return $result;
    }

    /**
     * Splits a long sentence into smaller chunks that respect the maxWords limit.
     *
     * @param string[] $words Array of words from the sentence
     * @return string[] Array of chunks
     */
    private function splitLongSentence(array $words): array
    {
        $chunks = [];
        $currentChunk = [];

        foreach ($words as $word) {
            if (count($currentChunk) >= $this->maxWords) {
                $chunks[] = implode(' ', $currentChunk);
                $currentChunk = [];
            }
            $currentChunk[] = $word;
        }

        if (!empty($currentChunk)) {
            $chunks[] = implode(' ', $currentChunk);
        }

        return $chunks;
    }
}
