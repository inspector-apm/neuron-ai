<?php

namespace NeuronAI\Tools\Toolkits\Tavily;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\Exceptions\ToolException;
use NeuronAI\Properties\BasicProperty;
use NeuronAI\Tools\Tool;

class TavilyExtractTool extends Tool
{
    protected Client $client;

    protected string $url = 'https://api.tavily.com/';

    protected array $options = [];

    /**
     * @param string $key Tavily API key.
     */
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
            'base_uri' => trim($this->url, '/').'/',
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    public function __invoke(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ToolException('Invalid URL.');
        }

        $result = $this->client->post('extract', [
            RequestOptions::JSON => \array_merge(
                $this->options,
                ['urls' => [$url]]
            )
        ])->getBody()->getContents();

        $result = \json_decode($result, true);

        return $result['results'][0];
    }

    public function withOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }
}
