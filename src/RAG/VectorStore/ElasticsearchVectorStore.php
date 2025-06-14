<?php

namespace NeuronAI\RAG\VectorStore;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use NeuronAI\RAG\Document;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use NeuronAI\RAG\DocumentModelInterface;

class ElasticsearchVectorStore implements VectorStoreInterface
{
    protected bool $vectorDimSet = false;

    protected array $filters = [];

    public function __construct(
        protected Client $client,
        protected string $index,
        protected int $topK = 4,
    ) {
    }

    protected function checkIndexStatus(DocumentModelInterface $document): void
    {
        /** @var Elasticsearch $existResponse */
        $existResponse = $this->client->indices()->exists(['index' => $this->index]);
        $existStatusCode = $existResponse->getStatusCode();

        if ($existStatusCode === 200) {
            // Map vector embeddings dimension on the fly adding a document.
            $this->mapVectorDimension(\count($document->getEmbedding()));
            return;
        }

        $properties = [
            'content' => [
                'type' => 'text',
            ],
            'sourceType' => [
                'type' => 'keyword',
            ],
            'sourceName' => [
                'type' => 'keyword',
            ]
        ];

        // Map custom fields
        foreach ($document->getCustomFields() as $name => $value) {
            $properties[$name] = [
                'type' => 'keyword',
            ];
        }

        $this->client->indices()->create([
            'index' => $this->index,
            'body' => [
                'mappings' => [compact('properties')],
            ],
        ]);
    }

    /**
     * @throws \Exception
     */
    public function addDocument(DocumentModelInterface $document): void
    {
        if (empty($document->embedding)) {
            throw new \Exception('Document embedding must be set before adding a document');
        }

        $this->checkIndexStatus($document);

        $this->client->index([
            'index' => $this->index,
            'body' => [
                'embedding' => $document->getEmbedding(),
                'content' => $document->getContent(),
                ...$document->getCustomFields(),
            ],
        ]);

        $this->client->indices()->refresh(['index' => $this->index]);
    }

    /**
     * @param  DocumentModelInterface[]  $documents
     *
     * @throws \Exception
     */
    public function addDocuments(array $documents): void
    {
        if ($documents === []) {
            return;
        }

        if (empty($documents[0]->getEmbedding())) {
            throw new \Exception('Document embedding must be set before adding a document');
        }

        $this->checkIndexStatus($documents[0]);

        /*
         * Generate a bulk payload
         */
        $params = ['body' => []];
        foreach ($documents as $document) {
            $params['body'][] = [
                'index' => [
                    '_index' => $this->index,
                ],
            ];
            $params['body'][] = [
                'embedding' => $document->getEmbedding(),
                'content' => $document->getContent(),
                ...$document->getCustomFields(),
            ];
        }
        $this->client->bulk($params);
        $this->client->indices()->refresh(['index' => $this->index]);
    }

    /**
     * {@inheritDoc}
     *
     * num_candidates are used to tune approximate kNN for speed or accuracy (see : https://www.elastic.co/guide/en/elasticsearch/reference/current/knn-search.html#tune-approximate-knn-for-speed-accuracy)
     * @return array
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function similaritySearch(array $embedding, string $documentModel): array
    {
        $searchParams = [
            'index' => $this->index,
            'body' => [
                'knn' => [
                    'field' => 'embedding',
                    'query_vector' => $embedding,
                    'k' => $this->topK,
                    'num_candidates' => \max(50, $this->topK * 4),
                ],
                'sort' => [
                    '_score' => [
                        'order' => 'desc',
                    ],
                ],
            ],
        ];

        // Hybrid search
        if (!empty($this->filters)) {
            $searchParams['body']['knn']['filter'] = $this->filters;
        }

        $response = $this->client->search($searchParams);

        return \array_map(function (array $item) use ($documentModel) {
            $document = new $documentModel($item['_source']['content']);
            $document->embedding = $item['_source']['embedding'];
            $document->sourceType = $item['_source']['sourceType'];
            $document->sourceName = $item['_source']['sourceName'];
            $document->score = $item['_score'];

            // Load custom fields
            $customFields = \array_intersect_key($item['_source'], $document->getCustomFields());
            foreach ($customFields as $fieldName => $value) {
                $document->{$fieldName} = $value;
            }

            return $document;
        }, $response['hits']['hits']);
    }

    /**
     * Map vector embeddings dimension on the fly.
     */
    private function mapVectorDimension(int $dimension): void
    {
        if ($this->vectorDimSet) {
            return;
        }

        $response = $this->client->indices()->getFieldMapping([
            'index' => $this->index,
            'fields' => 'embedding',
        ]);

        $mappings = $response[$this->index]['mappings'];
        if (
            \array_key_exists('embedding', $mappings)
            && $mappings['embedding']['mapping']['embedding']['dims'] === $dimension
        ) {
            return;
        }

        $this->client->indices()->putMapping([
            'index' => $this->index,
            'body' => [
                'properties' => [
                    'embedding' => [
                        'type' => 'dense_vector',
                        //'element_type' => 'float', // it's float by default
                        'dims' => $dimension,
                        'index' => true,
                        'similarity' => 'cosine',
                    ],
                ],
            ],
        ]);

        $this->vectorDimSet = true;
    }

    public function withFilters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }
}
