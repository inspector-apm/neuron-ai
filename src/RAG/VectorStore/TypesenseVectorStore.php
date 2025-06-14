<?php

namespace NeuronAI\RAG\VectorStore;

use Http\Client\Exception;
use NeuronAI\RAG\DocumentModelInterface;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

class TypesenseVectorStore implements VectorStoreInterface
{
    public function __construct(
        protected Client $client,
        protected string $collection,
        protected int $vectorDimension,
        protected string $topK = '4',
    ) {
    }

    /**
     * @throws Exception
     * @throws TypesenseClientError
     */
    public function checkIndexStatus(DocumentModelInterface $document): void
    {
        try {
            $this->client->collections[$this->collection]->retrieve();
            $this->checkVectorDimension(count($document->getEmbedding()));
            return;
        } catch (ObjectNotFound) {
            $fields = [
                [
                    'name' => 'content',
                    'type' => 'string',
                ],
                [
                    'name' => 'sourceType',
                    'type' => 'string',
                    'facet' => true,
                ],
                [
                    'name' => 'sourceName',
                    'type' => 'string',
                    'facet' => true,
                ],
                [
                    'name' => 'embedding',
                    'type' => 'float[]',
                    'num_dim' => $this->vectorDimension,
                ],
            ];

            // Map custom fields
            foreach ($document->getCustomFields() as $name => $value) {
                $fields[] = [
                    'name' => $name,
                    'type' => \gettype($value),
                    'facet' => true,
                ];
            }

            $this->client->collections->create([
                'name' => $this->collection,
                'fields' => $fields,
            ]);
        }
    }

    public function addDocument(DocumentModelInterface $document): void
    {
        if (empty($document->getEmbedding())) {
            throw new \Exception('document embedding must be set before adding a document');
        }

        $this->checkIndexStatus($document);

        $this->client->collections[$this->collection]->documents->create([
            'id' => $document->getId(), // Unique ID is required
            'content' => $document->getContent(),
            'embedding' => $document->getEmbedding(),
            'sourceType' => $document->getSourceType(),
            'sourceName' => $document->getSourceName(),
            ...$document->getCustomFields(),
        ]);
    }

    /**
     * @param DocumentModelInterface[] $documents
     * @throws Exception
     * @throws \JsonException
     * @throws TypesenseClientError
     */
    public function addDocuments(array $documents): void
    {
        if ($documents === []) {
            return;
        }

        if (empty($documents[0]->getEmbedding())) {
            throw new \Exception('document embedding must be set before adding a document');
        }

        $this->checkIndexStatus($documents[0]);

        $lines = [];
        foreach ($documents as $document) {
            $lines[] = json_encode([
                'id' => $document->getId(), // Unique ID is required
                'embedding' => $document->getEmbedding(),
                'content' => $document->getContent(),
                'sourceType' => $document->getSourceType(),
                'sourceName' => $document->getSourceName(),
                ...$document->getCustomFields(),
            ]);
        }

        $ndjson = implode("\n", $lines);

        $this->client->collections[$this->collection]->documents->import($ndjson);
    }

    public function similaritySearch(array $embedding, string $documentModel): array
    {
        $params = [
            'collection' => $this->collection,
            'q' => '*',
            'vector_query' => 'embedding:(' . json_encode($embedding) . ')',
            'exclude_fields' => 'embedding',
            'per_page' => $this->topK,
            'num_candidates' => \max(50, intval($this->topK) * 4),
        ];

        $searchRequests = ['searches' => [$params]];

        // Search parameters that are common to all searches go here
        $commonSearchParams =  [];

        $response = $this->client->multiSearch->perform($searchRequests, $commonSearchParams);
        return \array_map(function (array $hit) use ($documentModel) {
            $item = $hit['document'];
            $document = new $documentModel($item['content']);
            $document->embedding = $item['embedding'];
            $document->sourceType = $item['sourceType'];
            $document->sourceName = $item['sourceName'];
            $document->score = 1 - $hit['vector_distance'];

            // Load custom fields
            $customFields = \array_intersect_key($item, $document->getCustomFields());
            foreach ($customFields as $fieldName => $value) {
                $document->{$fieldName} = $value;
            }

            return $document;
        }, $response['results'][0]['hits']);
    }

    private function checkVectorDimension(int $dimension): void
    {
        $schema = $this->client->collections[$this->collection]->retrieve();

        $embeddingField = null;

        foreach ($schema['fields'] as $field) {
            if ($field['name'] === 'embedding') {
                $embeddingField = $field;
                break;
            }
        }

        if (
            \array_key_exists('num_dim', $embeddingField)
            && $embeddingField['num_dim'] === $dimension
        ) {
            return;
        }

        throw new \Exception(
            "Vector embeddings dimension {$dimension} must be the same as the initial setup {$this->vectorDimension} - ".
            json_encode($embeddingField)
        );
    }
}
