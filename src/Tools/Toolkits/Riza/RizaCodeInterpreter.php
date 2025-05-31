<?php

namespace NeuronAI\Tools\Toolkits\Riza;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\Properties\BasicProperty;
use NeuronAI\Tools\Tool;

class RizaCodeInterpreter extends Tool
{
    protected Client $client;

    protected string $url = 'https://api.riza.io/v1/';

    public function __construct(
        string $key,
        protected string $language = 'PHP',
    ) {
        parent::__construct(
            "execute_{$language}_code",
            "Execute {$language} scripts in a secure and isolated runtime environment."
        );

        $this->addProperty(
            new BasicProperty(
                'code',
                'string',
                'The code to execute.',
                true,
            )
        )->addProperty(
            new BasicProperty(
                'args',
                'array',
                "List of command line arguments to pass to the script (List of strings).",
                false,
            )
        )->addProperty(
            new BasicProperty(
                'env',
                'array',
                "Set of key-value pairs to add to the script's execution environment.",
                false,
            )
        )->setCallable($this);

        $this->client = new Client([
            'base_uri' => trim($this->url, '/').'/',
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
            ]
        ]);
    }

    public function __invoke(
        string $code,
        array $args = [],
        array $env = [],
    ) {
        $result = $this->client->post('execute', [
            RequestOptions::JSON => [
                'language' => $this->language,
                'code' => $code,
                'args' => $args,
                'env' => $env,
            ]
        ])->getBody()->getContents();

        return \json_decode($result, true);
    }
}
