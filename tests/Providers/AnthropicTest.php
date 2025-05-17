<?php

namespace NeuronAI\Tests\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use NeuronAI\Chat\Attachments\Document;
use NeuronAI\Chat\Attachments\Image;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Anthropic\Anthropic;
use PHPUnit\Framework\TestCase;

class AnthropicTest extends TestCase
{
    public function test_chat_request(): void
    {
        $sentRequests = [];
        $history = Middleware::history($sentRequests);
        $mockHandler = new MockHandler([
            new Response(
                status: 200,
                body: '{"model": "claude-3-7-sonnet-latest","role": "assistant","stop_reason": "end_turn","content":[{"type": "text","text": "How can I assist you today?"}],"usage": {"input_tokens": 19,"output_tokens": 29}}',
            ),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $stack->push($history);

        $client = new Client(['handler' => $stack]);

        $provider = (new Anthropic('', 'claude-3-7-sonnet-latest'))->setClient($client);

        $response = $provider->chat([new UserMessage('Hi')]);

        // Ensure we sent one request
        $this->assertCount(1, $sentRequests);
        $request = $sentRequests[0];

        // Ensure we have sent the expected request payload.
        $expectedResponse = [
            'model' => 'claude-3-7-sonnet-latest',
            'max_tokens' => 8192,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Hi',
                ],
            ],
        ];

        $this->assertSame($expectedResponse, json_decode($request['request']->getBody()->getContents(), true));
        $this->assertSame('How can I assist you today?', $response->getContent());
    }

    public function test_chat_with_base64_image(): void
    {
        $sentRequests = [];
        $history = Middleware::history($sentRequests);
        $mockHandler = new MockHandler([
            new Response(
                status: 200,
                body: '{"model": "claude-3-7-sonnet-latest","role": "assistant","stop_reason": "end_turn","content":[{"type": "text","text": "Understood."}],"usage": {"input_tokens": 50,"output_tokens": 10}}',
            ),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $stack->push($history);

        $client = new Client(['handler' => $stack]);
        $provider = (new Anthropic('', 'claude-3-7-sonnet-latest'))->setClient($client);

        $message = (new UserMessage('Describe this image'))
            ->addAttachment(new Image(
                image: 'base64_encoded_image_data',
                type: 'base64',
                mediaType: 'image/png'
            ));

        $provider->chat([$message]);

        $request = $sentRequests[0];

        $expectedResponse = [
            'model' => 'claude-3-7-sonnet-latest',
            'max_tokens' => 8192,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Describe this image',
                        ],
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => 'image/png',
                                'data' => 'base64_encoded_image_data',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expectedResponse, json_decode($request['request']->getBody()->getContents(), true));
    }

    public function test_chat_with_url_image(): void
    {
        $sentRequests = [];
        $history = Middleware::history($sentRequests);
        $mockHandler = new MockHandler([
            new Response(
                status: 200,
                body: '{"model": "claude-3-7-sonnet-latest","role": "assistant","stop_reason": "end_turn","content":[{"type": "text","text": "Understood."}],"usage": {"input_tokens": 50,"output_tokens": 10}}',
            ),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $stack->push($history);

        $client = new Client(['handler' => $stack]);
        $provider = (new Anthropic('', 'claude-3-7-sonnet-latest'))->setClient($client);

        $message = (new UserMessage('Describe this image'))
            ->addAttachment(new Image(image: 'https://example.com/image.png'));

        $provider->chat([$message]);

        $request = $sentRequests[0];

        $expectedResponse = [
            'model' => 'claude-3-7-sonnet-latest',
            'max_tokens' => 8192,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Describe this image',
                        ],
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'url',
                                'url' => 'https://example.com/image.png',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expectedResponse, json_decode($request['request']->getBody()->getContents(), true));
    }

    public function test_chat_with_base64_document(): void
    {
        $sentRequests = [];
        $history = Middleware::history($sentRequests);
        $mockHandler = new MockHandler([
            new Response(
                status: 200,
                body: '{"model": "claude-3-7-sonnet-latest","role": "assistant","stop_reason": "end_turn","content":[{"type": "text","text": "Understood."}],"usage": {"input_tokens": 50,"output_tokens": 10}}',
            ),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $stack->push($history);

        $client = new Client(['handler' => $stack]);
        $provider = (new Anthropic('', 'claude-3-7-sonnet-latest'))->setClient($client);

        $message = (new UserMessage('Describe this document'))
            ->addAttachment(new Document(
                document: 'base64_encoded_document_data',
                type: 'base64',
                mediaType: 'pdf'
            ));

        $provider->chat([$message]);

        $request = $sentRequests[0];

        $expectedResponse = [
            'model' => 'claude-3-7-sonnet-latest',
            'max_tokens' => 8192,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Describe this document',
                        ],
                        [
                            'type' => 'document',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => 'pdf',
                                'data' => 'base64_encoded_document_data',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expectedResponse, json_decode($request['request']->getBody()->getContents(), true));
    }

    public function test_chat_with_url_document(): void
    {
        $sentRequests = [];
        $history = Middleware::history($sentRequests);
        $mockHandler = new MockHandler([
            new Response(
                status: 200,
                body: '{"model": "claude-3-7-sonnet-latest","role": "assistant","stop_reason": "end_turn","content":[{"type": "text","text": "Understood."}],"usage": {"input_tokens": 50,"output_tokens": 10}}',
            ),
        ]);
        $stack = HandlerStack::create($mockHandler);
        $stack->push($history);

        $client = new Client(['handler' => $stack]);
        $provider = (new Anthropic('', 'claude-3-7-sonnet-latest'))->setClient($client);

        $message = (new UserMessage('Describe this document'))
            ->addAttachment(new Document(document: 'https://example.com/document.pdf'));

        $provider->chat([$message]);

        $request = $sentRequests[0];

        $expectedResponse = [
            'model' => 'claude-3-7-sonnet-latest',
            'max_tokens' => 8192,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Describe this document',
                        ],
                        [
                            'type' => 'document',
                            'source' => [
                                'type' => 'url',
                                'url' => 'https://example.com/document.pdf',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame($expectedResponse, json_decode($request['request']->getBody()->getContents(), true));
    }
}
