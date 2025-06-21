<?php

namespace NeuronAI\RAG;

use NeuronAI\Agent;
use NeuronAI\AgentInterface;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\AgentException;
use NeuronAI\Observability\Events\PostProcessed;
use NeuronAI\Observability\Events\PostProcessing;
use NeuronAI\Observability\Events\VectorStoreResult;
use NeuronAI\Observability\Events\VectorStoreSearching;
use NeuronAI\Exceptions\MissingCallbackParameter;
use NeuronAI\Exceptions\ToolCallableNotSet;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\RAG\PostProcessor\PostProcessorInterface;
use NeuronAI\RAG\PreProcessor\PreProcessorInterface;

/**
 * @method RAG withProvider(AIProviderInterface $provider)
 */
class RAG extends Agent
{
    use ResolveVectorStore;
    use ResolveEmbeddingProvider;

    /**
     * @var PreProcessorInterface[]
     */
    protected array $preProcessors = [];

    /**
     * @var PostProcessorInterface[]
     */
    protected array $postProcessors = [];

    /**
     * @deprecated TUse "chat" instead
     */
    public function answer(Message $question): Message
    {
        return $this->chat($question);
    }

    /**
     * @deprecated Use "stream" instead
     */
    public function answerStream(Message $question): \Generator
    {
        return $this->stream($question);
    }

    /**
     * @throws MissingCallbackParameter
     * @throws ToolCallableNotSet
     * @throws \Throwable
     */
    public function chat(Message|array $messages): Message
    {
        $question = \is_array($messages) ? $messages[0] : $messages;

        $this->notify('rag-start');

        $this->retrieval($question);

        $response = parent::chat($messages);

        $this->notify('rag-stop');
        return $response;
    }

    public function stream(Message|array $messages): \Generator
    {
        $question = \is_array($messages) ? $messages[0] : $messages;

        $this->notify('rag-start');

        $this->retrieval($question);

        yield from parent::stream($messages);

        $this->notify('rag-stop');
    }

    protected function retrieval(Message $question): void
    {
        $this->withDocumentsContext(
            $this->retrieveDocuments($question)
        );
    }

    /**
     * Set the system message based on the context.
     *
     * @param Document[] $documents
     */
    public function withDocumentsContext(array $documents): AgentInterface
    {
        $originalInstructions = $this->instructions();

        // Remove the old context to avoid infinite grow
        $newInstructions = $this->removeDelimitedContent($originalInstructions, '<EXTRA-CONTEXT>', '</EXTRA-CONTEXT>');

        $newInstructions .= '<EXTRA-CONTEXT>';
        foreach ($documents as $document) {
            $newInstructions .= $document->getContent().PHP_EOL.PHP_EOL;
        }
        $newInstructions .= '</EXTRA-CONTEXT>';

        $this->withInstructions(\trim($newInstructions));

        return $this;
    }

    /**
     * Retrieve relevant documents from the vector store.
     *
     * @return Document[]
     */
    public function retrieveDocuments(Message $question): array
    {
        $question = $this->applyPreProcessors($question);

        $this->notify('rag-vectorstore-searching', new VectorStoreSearching($question));

        $documents = $this->resolveVectorStore()->similaritySearch(
            $this->resolveEmbeddingsProvider()->embedText($question->getContent()),
        );

        $retrievedDocs = [];

        foreach ($documents as $document) {
            //md5 for removing duplicates
            $retrievedDocs[\md5($document->getContent())] = $document;
        }

        $retrievedDocs = \array_values($retrievedDocs);

        $this->notify('rag-vectorstore-result', new VectorStoreResult($question, $retrievedDocs));

        return $this->applyPostProcessors($question, $retrievedDocs);
    }

    /**
     * Apply a series of preprocessors to the asked question.
     *
     * @param Message $question The question to process.
     * @return Message The processed question.
     */
    protected function applyPreProcessors(Message $question): Message
    {
        foreach ($this->preProcessors() as $processor) {
            $question = $processor->process($question);
        }

        return $question;
    }

    /**
     * Apply a series of postprocessors to the retrieved documents.
     *
     * @param Message $question The question to process the documents for.
     * @param Document[] $documents The documents to process.
     * @return Document[] The processed documents.
     */
    protected function applyPostProcessors(Message $question, array $documents): array
    {
        foreach ($this->postProcessors() as $processor) {
            $this->notify('rag-postprocessing', new PostProcessing($processor::class, $question, $documents));
            $documents = $processor->process($question, $documents);
            $this->notify('rag-postprocessed', new PostProcessed($processor::class, $question, $documents));
        }

        return $documents;
    }

    /**
     * Feed the vector store with documents.
     *
     * @param Document[] $documents
     * @return void
     */
    public function addDocuments(array $documents): void
    {
        $this->resolveVectorStore()->addDocuments(
            $this->resolveEmbeddingsProvider()->embedDocuments($documents)
        );
    }

    /**
     * @throws AgentException
     */
    public function setPreProcessors(array $preProcessors): RAG
    {
        foreach ($preProcessors as $processor) {
            if (! $processor instanceof PreProcessorInterface) {
                throw new AgentException($processor::class." must implement PreProcessorInterface");
            }

            $this->preProcessors[] = $processor;
        }

        return $this;
    }

    /**
     * @throws AgentException
     */
    public function setPostProcessors(array $postProcessors): RAG
    {
        foreach ($postProcessors as $processor) {
            if (! $processor instanceof PostProcessorInterface) {
                throw new AgentException($processor::class." must implement PostProcessorInterface");
            }

            $this->postProcessors[] = $processor;
        }

        return $this;
    }

    /**
     * @return PreProcessorInterface[]
     */
    protected function preProcessors(): array
    {
        return $this->preProcessors;
    }

    /**
     * @return PostProcessorInterface[]
     */
    protected function postProcessors(): array
    {
        return $this->postProcessors;
    }
}
