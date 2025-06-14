<?php

namespace NeuronAI\Tests\VectorStore;

use NeuronAI\RAG\Document;
use NeuronAI\RAG\VectorStore\FileVectorStore;
use PHPUnit\Framework\TestCase;

class FileVectorStoreTest extends TestCase
{
    public function test_store_documents()
    {
        $document = new Document('Hello!');
        $document->embedding = [1, 2, 3];
        $document->id = 1;
        $document->sourceName = 'test';
        $document->sourceType = 'string';

        $document2 = new Document('Hello 2!');
        $document2->embedding = [3, 4, 5];
        $document2->id = 2;
        $document2->sourceName = 'test';
        $document2->sourceType = 'string';

        $store = new FileVectorStore(__DIR__, 1);
        $store->addDocuments([$document, $document2]);

        $results = $store->similaritySearch([1, 2, 3], Document::class);

        $this->assertCount(1, $results);
        $this->assertEquals($document->id, $results[0]->getId());
        $this->assertEquals($document->content, $results[0]->getContent());
        $this->assertEquals($document->embedding, $results[0]->getEmbedding());
        $this->assertEquals($document->sourceType, $results[0]->getSourceType());
        $this->assertEquals($document->sourceName, $results[0]->getSourceName());

        unlink(__DIR__.'/neuron.store');
    }

    public function test_custom_document_model()
    {
        $document = new class extends Document {
            public string $customProperty = 'customValue';
        };
        $document->embedding = [1, 2, 3];
        $document->id = 1;
        $document->sourceName = 'test';
        $document->sourceType = 'string';

        $store = new FileVectorStore(__DIR__, 1);
        $store->addDocuments([$document]);

        $results = $store->similaritySearch([1, 2, 3], $document::class);

        $this->assertCount(1, $results);
        $this->assertEquals($document->customProperty, $results[0]->customProperty);
    }
}
