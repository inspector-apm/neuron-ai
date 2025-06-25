<?php

declare(strict_types=1);

namespace NeuronAI\RAG\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use NeuronAI\RAG\Document;

class QdrantVectorStore implements VectorStoreInterface
{
    protected Client $client;

    public function __construct(
        protected string $collectionUrl, // like http://localhost:6333/collections/neuron-ai/
        protected string $key,
        protected int $topK = 4,
    ) {
        $this->client = new Client([
            'base_uri' => \trim($this->collectionUrl, '/').'/',
            'headers' => [
                'Content-Type' => 'application/json',
                'api-key' => $this->key,
            ]
        ]);
    }

    public function addDocument(Document $document): void
    {
        $this->client->put('points', [
            RequestOptions::JSON => [
                'points' => [
                    [
                        'id' => $document->getId(),
                        'payload' => [
                            'content' => $document->getContent(),
                            'sourceType' => $document->getSourceType(),
                            'sourceName' => $document->getSourceName(),
                            'metadata' => $document->metadata,
                        ],
                        'vector' => $document->getEmbedding(),
                    ]
                ]
            ]
        ]);
    }

    /**
     * Bulk save documents.
     *
     * @param Document[] $documents
     * @return void
     * @throws GuzzleException
     */
    public function addDocuments(array $documents): void
    {
        $points = \array_map(fn (Document $document) => [
            'id' => $document->getId(),
            'payload' => [
                'content' => $document->getContent(),
                'sourceType' => $document->getSourceType(),
                'sourceName' => $document->getSourceName(),
                ...$document->metadata,
            ],
            'vector' => $document->getEmbedding(),
        ], $documents);

        $this->client->put('points', [
            RequestOptions::JSON => [
                'operations' => [
                    ['upsert' => \compact('points')]
                ],
            ]
        ]);
    }

    public function similaritySearch(array $embedding): iterable
    {
        $response = $this->client->post('points/search', [
            RequestOptions::JSON => [
                'vector' => $embedding,
                'limit' => $this->topK,
                'with_payload' => true,
                'with_vector' => true,
            ]
        ])->getBody()->getContents();

        $response = \json_decode($response, true);

        return \array_map(function (array $item) {
            $document = new Document($item['payload']['content']);
            $document->id = $item['id'];
            $document->embedding = $item['vector'];
            $document->sourceType = $item['payload']['sourceType'];
            $document->sourceName = $item['payload']['sourceName'];
            $document->score = $item['score'];

            foreach ($item['payload'] as $name => $value) {
                if (!\in_array($name, ['content', 'sourceType', 'sourceName', 'score', 'embedding', 'id'])) {
                    $document->addMetadata($name, $value);
                }
            }

            return $document;
        }, $response['result']);
    }
}
