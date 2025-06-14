<?php

namespace NeuronAI\RAG\VectorStore;

use NeuronAI\Exceptions\VectorStoreException;
use NeuronAI\RAG\DocumentModelInterface;
use NeuronAI\RAG\VectorStore\Search\SimilaritySearch;

class MemoryVectorStore implements VectorStoreInterface
{
    /**
     * @var DocumentModelInterface[]
     */
    private array $documents = [];

    public function __construct(protected int $topK = 4)
    {
    }

    public function addDocument(DocumentModelInterface $document): void
    {
        $this->documents[] = $document;
    }

    public function addDocuments(array $documents): void
    {
        $this->documents = \array_merge($this->documents, $documents);
    }

    public function similaritySearch(array $embedding, string $documentModel): array
    {
        $distances = [];

        foreach ($this->documents as $index => $document) {
            if (empty($document->embedding)) {
                throw new VectorStoreException("Document with the following content has no embedding: {$document->getContent()}");
            }
            $dist = $this->cosineSimilarity($embedding, $document->getEmbedding());
            $distances[$index] = $dist;
        }

        \asort($distances); // Sort by distance (ascending).

        $topKIndices = \array_slice(\array_keys($distances), 0, $this->topK, true);

        return \array_reduce($topKIndices, function ($carry, $index) use ($distances) {
            $document = $this->documents[$index];
            $document->setScore(1 - $distances[$index]);
            $carry[] = $document;
            return $carry;
        }, []);
    }


    public function cosineSimilarity(array $vector1, array $vector2): float
    {
        return SimilaritySearch::cosine($vector1, $vector2);
    }
}
