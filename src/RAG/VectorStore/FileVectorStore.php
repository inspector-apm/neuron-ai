<?php

namespace NeuronAI\RAG\VectorStore;

use NeuronAI\Exceptions\VectorStoreException;
use NeuronAI\RAG\DocumentModelInterface;
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
        return $this->directory . DIRECTORY_SEPARATOR . $this->name.$this->ext;
    }

    public function addDocument(DocumentModelInterface $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        $this->appendToFile(
            \array_map(fn (DocumentModelInterface $document) => $document->jsonSerialize(), $documents)
        );
    }

    public function similaritySearch(array $embedding, string $documentModel): array
    {
        $topItems = [];

        foreach ($this->getLine($this->getFilePath()) as $document) {
            $document = \json_decode($document, true);

            if (empty($document['embedding'])) {
                throw new VectorStoreException("Document with the following content has no embedding: {$document['content']}");
            }
            $dist = $this->cosineSimilarity($embedding, $document['embedding']);

            $topItems[] = compact('dist', 'document');

            \usort($topItems, fn ($a, $b) => $a['dist'] <=> $b['dist']);

            if (\count($topItems) > $this->topK) {
                $topItems = \array_slice($topItems, 0, $this->topK, true);
            }
        }

        return \array_reduce($topItems, function ($carry, $item) use ($documentModel) {
            $itemDoc = $item['document'];
            $document = new $documentModel($itemDoc['content']);
            $document->embedding = $itemDoc['embedding'];
            $document->sourceType = $itemDoc['sourceType'];
            $document->sourceName = $itemDoc['sourceName'];
            $document->id = $itemDoc['id'];
            $document->score = 1 - $item['dist'];

            $customFields = \array_intersect_key($itemDoc, $document->getCustomFields());
            foreach ($customFields as $fieldName => $value) {
                $document->{$fieldName} = $value;
            }

            $carry[] = $document;
            return $carry;
        }, []);
    }


    protected function cosineSimilarity(array $vector1, array $vector2): float
    {
        return SimilaritySearch::cosine($vector1, $vector2);
    }

    protected function appendToFile(array $vectors): void
    {
        \file_put_contents(
            $this->getFilePath(),
            implode(PHP_EOL, \array_map(fn (array $vector) => \json_encode($vector), $vectors)).PHP_EOL,
            FILE_APPEND
        );
    }

    protected function getLine($file): \Generator
    {
        $f = fopen($file, 'r');

        try {
            while ($line = fgets($f)) {
                yield $line;
            }
        } finally {
            fclose($f);
        }
    }
}
