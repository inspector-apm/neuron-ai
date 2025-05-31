<?php

namespace NeuronAI\Tools\Toolkits\Jina;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\Properties\BasicProperty;
use NeuronAI\Tools\Tool;

class JinaWebSearch extends Tool
{
    protected Client $client;

    public function __construct(
        string $key,
        array $topics = [],
    ) {
        parent::__construct(
            'web_search',
            'Use this tool to search the web for additional information '.
            (!empty($topics) ? 'about '.implode(', ', $topics).', or ' : '').
            'if the question is outside the scope of the context you have.'
        );

        $this->addProperty(
            new BasicProperty(
                'search_query',
                'string',
                'The search query to perform web search.',
                true
            )
        )->setCallable($this);

        $this->client = new Client([
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json',
                'X-Respond-With' => 'no-content',

                // Uncomment this line to return a JSON response.
                //'Accept' => 'application/json',
            ]
        ]);
    }

    public function __invoke(string $search_query): string
    {
        return $this->client->post('https://s.jina.ai/', [
            RequestOptions::JSON => [
                'q' => $search_query,
            ]
        ])->getBody()->getContents();
    }
}
