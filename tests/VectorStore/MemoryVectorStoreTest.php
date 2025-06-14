<?php

namespace NeuronAI\Tests\VectorStore;

use NeuronAI\RAG\Document;
use NeuronAI\RAG\VectorStore\MemoryVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;
use PHPUnit\Framework\TestCase;

class MemoryVectorStoreTest extends TestCase
{
    protected array $embedding;

    protected function setUp(): void
    {
        // embedding "Hello World!"
        $this->embedding = json_decode(file_get_contents(__DIR__ . '/../stubs/hello-world.embeddings'), true);
    }

    public function test_memory_store_instance()
    {
        $store = new MemoryVectorStore();
        $this->assertInstanceOf(VectorStoreInterface::class, $store);
    }

    public function test_add_document_and_search()
    {
        $this->expectNotToPerformAssertions();
        $document = new Document('Hello World!');
        $document->embedding = $this->embedding;

        $store = new MemoryVectorStore();
        $store->addDocument($document);

        $results = $store->similaritySearch($this->embedding, Document::class);
    }

    public function test_similarity_search_with_scores()
    {
        $vectorStore = new MemoryVectorStore();

        $doc1 = new Document("Document 1");
        $doc1->embedding = [1, 0];
        $doc2 = new Document("Document 2");
        $doc2->embedding = [0, 1];
        $doc3 = new Document("Document 3");
        $doc3->embedding = [0.5, 0.5];

        $vectorStore->addDocuments([$doc1, $doc2, $doc3]);

        $results = $vectorStore->similaritySearch([1, 0], Document::class);

        $this->assertCount(3, $results);
        $this->assertGreaterThanOrEqual($results[1]->getScore(), $results[0]->getScore());
        $this->assertGreaterThanOrEqual($results[2]->getScore(), $results[1]->getScore());
    }

    public function test_custom_document_model()
    {
        $document = new class extends Document {
            public string $customProperty = 'customValue';
        };
        $document->embedding = [1, 0];

        $vectorStore = new MemoryVectorStore();
        $vectorStore->addDocuments([$document]);

        $results = $vectorStore->similaritySearch([1, 0], $document::class);

        $this->assertCount(1, $results);
        $this->assertEquals($document->customProperty, $results[0]->customProperty);
    }
}
