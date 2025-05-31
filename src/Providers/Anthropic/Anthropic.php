<?php

namespace NeuronAI\Providers\Anthropic;

use GuzzleHttp\Client;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\HasGuzzleClient;
use NeuronAI\Properties\BasicProperty;
use NeuronAI\Properties\PropertyInterface;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\HandleWithTools;
use NeuronAI\Providers\MessageMapperInterface;
use NeuronAI\Tools\ToolInterface;

class Anthropic implements AIProviderInterface
{
    use HasGuzzleClient;
    use HandleWithTools;
    use HandleChat;
    use HandleStream;
    use HandleStructured;

    /**
     * The main URL of the provider API.
     *
     * @var string
     */
    protected string $baseUri = 'https://api.anthropic.com/v1/';

    /**
     * System instructions.
     * https://docs.anthropic.com/claude/docs/system-prompts#how-to-use-system-prompts
     *
     * @var string|null
     */
    protected ?string $system = null;

    /**
     * The component responsible for mapping the NeuronAI Message to the AI provider format.
     *
     * @var MessageMapperInterface
     */
    protected MessageMapperInterface $messageMapper;

    /**
     * AnthropicClaude constructor.
     */
    public function __construct(
        protected string $key,
        protected string $model,
        protected string $version = '2023-06-01',
        protected int $max_tokens = 8192,
        protected array $parameters = [],
    ) {
        $this->client = new Client([
            'base_uri' => trim($this->baseUri, '/').'/',
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->key,
                'anthropic-version' => $version,
            ]
        ]);
    }

    /**
     * @inerhitDoc
     */
    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        $this->system = $prompt;
        return $this;
    }

    public function messageMapper(): MessageMapperInterface
    {
        if (!isset($this->messageMapper)) {
            $this->messageMapper = new MessageMapper();
        }
        return $this->messageMapper;
    }

    public function generateToolsPayload(): array
    {
        return \array_map(function (ToolInterface $tool) {
            $properties = \array_reduce($tool->getProperties(), function ($carry, PropertyInterface $property) {
                $carry[$property->getName()] = [
                    'type' => $property->getType(),
                    'description' => $property->getDescription(),
                ];

                if ($property instanceof BasicProperty && !empty($property->getEnum())) {
                    $carry[$property->getName()]['enum'] = $property->getEnum();
                }

                return $carry;
            }, []);

            return [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'input_schema' => [
                    'type' => 'object',
                    'properties' => !empty($properties) ? $properties : null,
                    'required' => $tool->getRequiredProperties(),
                ],
            ];
        }, $this->tools);
    }

    public function createToolCallMessage(array $content): Message
    {
        $tool = $this->findTool($content['name'])
            ->setInputs($content['input'])
            ->setCallId($content['id']);

        // During serialization and deserialization PHP convert the original empty object {} to empty array []
        // causing an error on the Anthropic API. If there are no inputs, we need to restore the empty JSON object.
        if (empty($content['input'])) {
            $content['input'] = new \stdClass();
        }

        return new ToolCallMessage(
            [$content],
            [$tool] // Anthropic call one tool at a time. So we pass an array with one element.
        );
    }
}
