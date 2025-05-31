<?php

namespace NeuronAI\Tools\Toolkits\Jina;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\Exceptions\ToolException;
use NeuronAI\Properties\BasicProperty;
use NeuronAI\Tools\Tool;

class JinaUrlReader extends Tool
{
    protected Client $client;

    public function __construct(string $key)
    {
        parent::__construct(
            'url_reader',
            'Get the content of a URL in markdown format.'
        );

        $this->addProperty(
            new BasicProperty(
                'url',
                'string',
                'The URL to read.',
                true
            ),
        )->setCallable($this);

        $this->client = new Client([
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Return-Format' => 'Markdown',

                // Uncomment this line to return a JSON response.
                //'Accept' => 'application/json',
            ]
        ]);
    }

    public function __invoke(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ToolException('Invalid URL.');
        }

        return $this->client->post('https://r.jina.ai/', [
            RequestOptions::JSON => [
                'url' => $url,
            ]
        ])->getBody()->getContents();
    }
}
