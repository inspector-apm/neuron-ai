<?php

namespace NeuronAI\Providers\Embeddings;

use NeuronAI\RAG\Document;
use GuzzleHttp\Client;

class VoyageEmbeddingProvider extends AbstractEmbeddingProvider
{
    protected Client $client;

    public function __construct(
        string $key,
        protected string $model
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.voyageai.com/v1/embeddings',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $key,
            ],
        ]);
    }

    public function embedText(string $text): array
    {
        $response = $this->client->post('', [
            'json' => [
                'model' => $this->model,
                'input' => $text,
            ]
        ]);

        $response = \json_decode($response->getBody()->getContents(), true);

        return $response['data'][0]['embedding'];
    }

    public function embedDocument(Document $document): Document
    {
        $text = $document->formattedContent ?? $document->content;
        $document->embedding = $this->embedText($text);

        return $document;
    }
}
