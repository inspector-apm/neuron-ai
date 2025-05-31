<?php

namespace NeuronAI\Tools\Toolkits\Tavily;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\Properties\BasicProperty;
use NeuronAI\Tools\Tool;

class TavilySearchTool extends Tool
{
    protected Client $client;

    protected string $url = 'https://api.tavily.com/';

    protected array $options = [
        'search_depth' => 'basic',
        'chunks_per_source' => 3,
        'max_results' => 1,
    ];

    /**
     * @param string $key Tavily API key.
     * @param array $topics Explicit the topics you want to force the Agent to perform web search.
     */
    public function __construct(
        string $key,
        protected array $topics = [],
    ) {
        parent::__construct(
            'web_search',
            'Use this tool to search the web for additional information '.
            (!empty($this->topics) ? 'about '.implode(', ', $this->topics).', or ' : '').
            'if the question is outside the scope of the context you have.'
        );

        $this->initTool();

        $this->client = new Client([
            'base_uri' => trim($this->url, '/').'/',
            'headers' => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    protected function initTool()
    {
        $this->addProperty(
            new BasicProperty(
                'search_query',
                'string',
                'The search query to perform web search.',
                true
            ),
        )->addProperty(
            new BasicProperty(
                'topic',
                'string',
                'Explicit the topic you want to perform the web search on.',
                false,
                ['general', 'news']
            ),
        )->addProperty(
            new BasicProperty(
                'time_range',
                'string',
                '',
                false,
                ['day, week, month, year']
            )
        )->addProperty(
            new BasicProperty(
                'days',
                'integer',
                '',
                false,
            )
        );

        $this->setCallable($this);
    }

    public function __invoke(
        string $search_query,
        string $topic = 'general',
        string $time_range = 'day',
        int $days = 7,
    ) {
        $result = $this->client->post('search', [
            RequestOptions::JSON => \array_merge(
                compact('topic', 'time_range', 'days'),
                $this->options,
                [
                    'query' => $search_query,
                ]
            )
        ])->getBody()->getContents();

        $result = \json_decode($result, true);

        return [
            'answer' => $result['answer'],
            'results' => \array_map(fn ($item) => [
                'title' => $item['title'],
                'url' => $item['url'],
                'content' => $item['content'],
            ], $result['results']),
        ];
    }

    public function withOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }
}
