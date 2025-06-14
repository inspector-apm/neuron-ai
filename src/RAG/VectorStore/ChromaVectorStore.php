<?php

namespace NeuronAI\RAG\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\RAG\DocumentModelInterface;

class ChromaVectorStore implements VectorStoreInterface
{
    protected Client $client;

    public function __construct(
        protected string $collection,
        protected string $host = 'http://localhost:8000',
        protected int $topK = 5,
    ) {
    }

    protected function getClient(): Client
    {
        if (isset($this->client)) {
            return $this->client;
        }
        return $this->client = new Client([
            'base_uri' => trim($this->host, '/')."/api/v1/collections/{$this->collection}/",
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    public function addDocument(DocumentModelInterface $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        $this->getClient()->post('upsert', [
            RequestOptions::JSON => $this->mapDocuments($documents),
        ])->getBody()->getContents();
    }

    public function similaritySearch(array $embedding, string $documentModel): iterable
    {
        $response = $this->getClient()->post('query', [
            RequestOptions::JSON => [
                'queryEmbeddings' => $embedding,
                'nResults' => $this->topK,
            ]
        ])->getBody()->getContents();

        $response = \json_decode($response, true);

        // Map the result
        $size = \count($response['distances']);
        $result = [];
        for ($i = 0; $i < $size; $i++) {
            $document = new $documentModel();
            $document->id = $response['ids'][$i] ?? \uniqid();
            $document->embedding = $response['embeddings'][$i];
            $document->content = $response['documents'][$i];
            $document->sourceType = $response['metadatas'][$i]['sourceType'] ?? null;
            $document->sourceName = $response['metadatas'][$i]['sourceName'] ?? null;
            $document->score = $response['distances'][$i];

            // Load custom fields
            $customFields = \array_intersect_key($response['metadatas'][$i], $document->getCustomFields());
            foreach ($customFields as $fieldName => $value) {
                $document->{$fieldName} = $value;
            }

            $result[] = $document;
        }

        return $result;
    }

    /**
     * @param DocumentModelInterface[] $documents
     * @return array
     */
    protected function mapDocuments(array $documents): array
    {
        $payload = [
            'ids' => [],
            'documents' => [],
            'embeddings' => [],
            'metadatas' => [],

        ];
        foreach ($documents as $document) {
            $payload['ids'][] = $document->getId();
            $payload['documents'][] = $document->getContent();
            $payload['embeddings'][] = $document->getEmbedding();
            $payload['metadatas'][] = [
                'sourceType' => $document->getSourceType(),
                'sourceName' => $document->getSourceName(),
                ...$document->getCustomFields(),
            ];
        }

        return $payload;
    }
}
