<?php

namespace NeuronAI\Tools\Toolkits\Riza;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\Properties\BasicProperty;
use NeuronAI\Tools\Tool;

class RizaFunctionExecutor extends Tool
{
    protected Client $client;

    protected string $url = 'https://api.riza.io/v1/';

    public function __construct(
        string $key,
        protected string $language = 'JavaScript', // Python, JavaScript, and TypeScript (no PHP unfortunately)
    ) {
        parent::__construct(
            "execute_{$language}_function",
            "Execute {$language} function and get the result."
        );

        $this->addProperty(
            new BasicProperty(
                'code',
                'string',
                'The function code to execute.',
                true,
            )
        )->addProperty(
            new BasicProperty(
                'input',
                'array',
                'The input arguments to execute the function.',
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
        array $input = [],
        array $env = [],
    ) {
        $result = $this->client->post('execute-function', [
            RequestOptions::JSON => [
                'language' => $this->language,
                'code' => $code,
                'input' => $input,
                'env' => $env,
            ]
        ])->getBody()->getContents();

        return \json_decode($result, true);
    }
}
