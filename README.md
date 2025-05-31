# Create Full-Featured AI Agents As Standalone Components In Any PHP Application

[![Latest Stable Version](https://poser.pugx.org/inspector-apm/neuron-ai/v/stable)](https://packagist.org/packages/inspector-apm/neuron-ai)
[![Total Downloads](http://poser.pugx.org/inspector-apm/neuron-ai/downloads)](https://packagist.org/packages/inspector-apm/neuron-ai)

> Before moving on, support the community giving a GitHub star ⭐️. Thank you!

[**Video Tutorial**](https://www.youtube.com/watch?v=fJSX8wWIDO8)

[![Neuron & Inspector](./docs/img/youtube.png)](https://www.youtube.com/watch?v=fJSX8wWIDO8)

---

## Requirements

- PHP: ^8.1

## Official documentation

**[Go to the official documentation](https://neuron.inspector.dev/)**

## Forum

You can post questions and feedback on the [Inspector Forum](https://github.com/inspector-apm/neuron-ai/discussions).

## Neuron AI Examples

- [Install](#install)
- [Create an Agent](#create)
- [Talk to the Agent](#talk)
- [Supported LLM Providers](#providers)
- [Tools & Function Calls](#tools)
- [MCP server connector](#mcp)
- [Implement RAG systems](#rag)
- [Structured Output](#structured)
- [Observability](#observability)
- [Official Documentation](#documentation)

<a name="install">

## Install

Install the latest version of the package:

```
composer require inspector-apm/neuron-ai
```

<a name="create">

## Create an Agent

Neuron provides you with the Agent class you can extend to inherit the main features of the framework,
and create fully functional agents. This class automatically manages some advanced mechanisms for you such as memory,
tools and function calls, up to the RAG systems. You can go deeper into these aspects in the [documentation](https://docs.neuron-ai.dev).
In the meantime, let's create the first agent, extending the `NeuronAI\Agent` class:

```php
use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

class YouTubeAgent extends Agent
{
    public function provider(): AIProviderInterface
    {
        return new Anthropic(
            key: 'ANTHROPIC_API_KEY',
            model: 'ANTHROPIC_MODEL',
        );
    }

    public function instructions(): string
    {
        return new SystemPrompt(
            background: ["You are an AI Agent specialized in writing YouTube video summaries."],
            steps: [
                "Get the url of a YouTube video, or ask the user to provide one.",
                "Use the tools you have available to retrieve the transcription of the video.",
                "Write the summary.",
            ],
            output: [
                "Write a summary in a paragraph without using lists. Use just fluent text.",
                "After the summary add a list of three sentences as the three most important take away from the video.",
            ]
        );
    }
}
```

The `SystemPrompt` class is designed to take your base instructions and build a consistent prompt for the underlying model
reducing the effort for prompt engineering.

<a name="talk">

## Talk to the Agent

Send a prompt to the agent to get a response from the underlying LLM:

```php
use NeuronAI\Observability\AgentMonitoring;

// https://docs.neuron-ai.dev/advanced/observability
$inspector = new \Inspector\Inspector(
    new \Inspector\Configuration('INSPECTOR_INGESTION_KEY')
);

$agent = YouTubeAgent::make()->observe(new AgentMonitoring($inspector));


$response = $agent->chat(
    new UserMessage("Hi, I'm Valerio. Who are you?")
);
echo $response->getContent();
// I'm a friendly YouTube assistant to help you summarize videos.


$response = $agent->chat(
    new UserMessage("Do you know my name?")
);
echo $response->getContent();
// Your name is Valerio, as you said in your introduction.
```

As you can see in the example above, the Agent automatically has memory of the ongoing conversation. Learn more about memory in the [documentation](https://docs.neuron-ai.dev/chat-history-and-memory).

<a name="providers">

## Supported LLM Providers

With Neuron you can switch between LLM providers with just one line of code, without any impact on your agent implementation.
Supported providers:

- Anthropic
- Ollama (also available as an [embeddings provider](https://docs.neuron-ai.dev/components/embeddings-provider#ollama))
- OpenAI (also available as an [embeddings provider](https://docs.neuron-ai.dev/components/embeddings-provider#openai))
- Gemini

<a name="tools">

## Tools & Function Calls

You can add the ability to perform concrete tasks to your Agent with an array of `Tool`:

```php
use NeuronAI\Agent;
use NeuronAI\Properties\BasicProperty;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\SystemPrompt;
use NeuronAI\Tools\Tool;

class YouTubeAgent extends Agent
{
    public function provider(): AIProviderInterface
    {
        return new Anthropic(
            key: 'ANTHROPIC_API_KEY',
            model: 'ANTHROPIC_MODEL',
        );
    }

    public function instructions(): string
    {
        return new SystemPrompt(
            background: ["You are an AI Agent specialized in writing YouTube video summaries."],
            steps: [
                "Get the url of a YouTube video, or ask the user to provide one.",
                "Use the tools you have available to retrieve the transcription of the video.",
                "Write the summary.",
            ],
            output: [
                "Write a summary in a paragraph without using lists. Use just fluent text.",
                "After the summary add a list of three sentences as the three most important take away from the video.",
            ]
        );
    }

    public function tools(): array
    {
        return [
            Tool::make(
                'get_transcription',
                'Retrieve the transcription of a youtube video.',
            )->addProperty(
                new BasicProperty(
                    name: 'video_url',
                    type: 'string',
                    description: 'The URL of the YouTube video.',
                    required: true
                )
            )->setCallable(function (string $video_url) {
                // ... retrieve the video transcription
            })
        ];
    }
}
```

Learn more about Tools in the [documentation](https://docs.neuron-ai.dev/tools-and-function-calls).

<a name="mcp">

## MCP server connector

Instead of implementing tools manually, you can connect tools exposed by an MCP server with the `McpConnector` component:

```php
use NeuronAI\Agent;
use NeuronAI\MCP\McpConnector;
use NeuronAI\Properties\BasicProperty;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Tools\Tool;

class YouTubeAgent extends Agent
{
    public function provider(): AIProviderInterface
    {
        return new Anthropic(
            key: 'ANTHROPIC_API_KEY',
            model: 'ANTHROPIC_MODEL',
        );
    }

    public function instructions(): string
    {
        return new SystemPrompt(
            background: ["Act as an expert of SEO (Search Engine Optimization)."]
            steps: [
                "Analyze a text of an article.",
                "Provide suggestions on how the content can be improved to get a better rank on Google search."
            ],
            output: ["Structure your analysis in sections. One for each suggestion."]
        );
    }

    public function tools(): array
    {
        return [
            // Connect an MCP server
            ...McpConnector::make([
                'command' => 'npx',
                'args' => ['-y', '@modelcontextprotocol/server-everything'],
            ])->tools(),

            // Implement your custom tools
            Tool::make(
                'get_transcription',
                'Retrieve the transcription of a youtube video.',
            )->addProperty(
                new BasicProperty(
                    name: 'video_url',
                    type: 'string',
                    description: 'The URL of the YouTube video.',
                    required: true
                )
            )->setCallable(function (string $video_url) {
                // ... retrieve the video transcription
            })
        ];
    }
}
```

Learn more about MCP connector in the [documentation](https://docs.neuron-ai.dev/advanced/mcp-servers-connection).

<a name="rag">

## Implement RAG systems

For RAG use case, you must extend the `NeuronAI\RAG\RAG` class instead of the default Agent class.

To create a RAG you need to attach some additional components other than the AI provider, such as a `vector store`,
and an `embeddings provider`.

Here is an example of a RAG implementation:

```php
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\Embeddings\VoyageEmbeddingProvider;
use NeuronAI\RAG\RAG;
use NeuronAI\RAG\VectorStore\PineconeVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;

class MyChatBot extends RAG
{
    public function provider(): AIProviderInterface
    {
        return new Anthropic(
            key: 'ANTHROPIC_API_KEY',
            model: 'ANTHROPIC_MODEL',
        );
    }

    public function embeddings(): EmbeddingsProviderInterface
    {
        return new VoyageEmbeddingProvider(
            key: 'VOYAGE_API_KEY',
            model: 'VOYAGE_MODEL'
        );
    }

    public function vectorStore(): VectorStoreInterface
    {
        return new PineconeVectorStore(
            key: 'PINECONE_API_KEY',
            indexUrl: 'PINECONE_INDEX_URL'
        );
    }
}
```

Learn more about RAG in the [documentation](https://docs.neuron-ai.dev/rag).

<a name="structured">

## Structured Output
For many applications, such as chatbots, Agents need to respond to users directly in natural language.
However, there are scenarios where we need Agents to understand natural language, but output in a structured format.

One common use-case is extracting data from text to insert into a database or use with some other downstream system.
This guide covers a few strategies for getting structured outputs from the agent.

```php
use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\Observability\AgentMonitoring;

// Define the output structure with a PHP class, including validation constraints.
class Person
{
    #[SchemaProperty(description: 'The user name')]
    public string $name;

    #[SchemaProperty(description: 'What the user love to eat')]
    public string $preference;
}

// https://docs.neuron-ai.dev/advanced/observability
$inspector = new \Inspector\Inspector(
    new \Inspector\Configuration('INSPECTOR_INGESTION_KEY')
);

// Talk to the agent requiring the structured output
$person = MyAgent::make()
    ->observe(new AgentMonitoring($inspector))
    ->structured(
        new UserMessage("I'm John and I like pizza!"),
        Person::class
    );

echo $person->name ' like '.$person->preference;
// John like pizza
```

Learn more about Structured Output on the [documentation](https://docs.neuron-ai.dev/advanced/structured-output).

<a name="observability">

## Observability

Neuron offers a built-in integration with [Inspector.dev](https://inspector.dev) to monitor the performance of your agents
and detect unexpected errors in real time.

You have to install the Inspector package based on your development environment. We provide integration packages
for [PHP](https://github.com/inspector-apm/inspector-php), [Laravel](https://github.com/inspector-apm/inspector-laravel),
[Symfony](https://github.com/inspector-apm/inspector-symfony), [CodeIgniter](https://github.com/inspector-apm/inspector-codeigniter),
[Drupal](https://git.drupalcode.org/project/inspector_monitoring).

Attach the `AgentMonitoring` component to the agent to monitor the internal execution timeline in the Inspector dashboard.
If the agent fires an error, you will be alerted in real-time. You can connect several notification channels like email, slack, discord, telegram, and more.
Here is a code example in a legacy PHP script:

```php
use NeuronAI\Observability\AgentMonitoring;

// https://docs.neuron-ai.dev/advanced/observability
$inspector = new \Inspector\Inspector(
    new \Inspector\Configuration('INSPECTOR_INGESTION_KEY')
);

// Attach monitoring to the Agent
$response = MyAgent::make()
    ->observe(new AgentMonitoring($inspector))
    ->chat(...);
```

![](./docs/img/neuron-observability.png)

> If you use a framework like Laravel, Symfony, or CodeIgniter, the connection is even easier,
> since you already have the Inspector instance in the container.

Learn more about Observability in the [documentation](https://docs.neuron-ai.dev/advanced/observability).

<a name="documentation">

## Official documentation

**[Go to the official documentation](https://neuron.inspector.dev/)**


