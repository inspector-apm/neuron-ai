<?php

declare(strict_types=1);

namespace NeuronAI\RAG\VectorStore;

use NeuronAI\Exceptions\VectorStoreException;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\VectorStore\Search\SimilaritySearch;

class FileVectorStore implements VectorStoreInterface
{
    public function __construct(
        protected string $directory,
        protected int $topK = 4,
        protected string $name = 'neuron',
        protected string $ext = '.store'
    ) {
        if (!\is_dir($this->directory)) {
            throw new VectorStoreException("Directory '{$this->directory}' does not exist");
        }
    }

    protected function getFilePath(): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . $this->name.$this->ext;
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        $this->appendToFile(
            \array_map(fn (Document $document) => $document->jsonSerialize(), $documents)
        );
    }

    public function similaritySearch(array $embedding): array
    {
        $topItems = [];

        foreach ($this->getLine($this->getFilePath()) as $document) {
            $document = \json_decode($document, true);

            if (empty($document['embedding'])) {
                throw new VectorStoreException("Document with the following content has no embedding: {$document['content']}");
            }
            $dist = $this->cosineSimilarity($embedding, $document['embedding']);

            $topItems[] = \compact('dist', 'document');

            \usort($topItems, fn (array $a, array $b): int => $a['dist'] <=> $b['dist']);

            if (\count($topItems) > $this->topK) {
                $topItems = \array_slice($topItems, 0, $this->topK, true);
            }
        }

        return \array_map(function (array $item): Document {
            $itemDoc = $item['document'];
            $document = new Document($itemDoc['content']);
            $document->embedding = $itemDoc['embedding'];
            $document->sourceType = $itemDoc['sourceType'];
            $document->sourceName = $itemDoc['sourceName'];
            $document->id = $itemDoc['id'];
            $document->score = 1 - $item['dist'];
            $document->metadata = $itemDoc['metadata'] ?? [];

            return $document;
        }, $topItems);
    }


    protected function cosineSimilarity(array $vector1, array $vector2): float
    {
        return SimilaritySearch::cosine($vector1, $vector2);
    }

    protected function appendToFile(array $documents): void
    {
        \file_put_contents(
            $this->getFilePath(),
            \implode(\PHP_EOL, \array_map(fn (array $vector) => \json_encode($vector), $documents)).\PHP_EOL,
            \FILE_APPEND
        );
    }

    protected function getLine(string $filename): \Generator
    {
        $f = \fopen($filename, 'r');

        try {
            while ($line = \fgets($f)) {
                yield $line;
            }
        } finally {
            \fclose($f);
        }
    }
}
