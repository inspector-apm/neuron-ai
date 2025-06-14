<?php

namespace NeuronAI\Tests;

use NeuronAI\RAG\DataLoader\StringDataLoader;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\DataLoader\DocumentSplitter;
use PHPUnit\Framework\TestCase;

class DataLoaderTest extends TestCase
{
    public function test_string_data_loader()
    {
        $documents = StringDataLoader::for('test')->getDocuments();
        $this->assertCount(1, $documents);
        $this->assertEquals('test', $documents[0]->getContent());
    }

    public function test_split_long_text()
    {
        $doc = new Document(file_get_contents(__DIR__.'/stubs/long-text.txt'));

        $documents = DocumentSplitter::splitDocument($doc);
        $this->assertCount(7, $documents);

        $documents = DocumentSplitter::splitDocument($doc, 500);
        $this->assertCount(14, $documents);

        $documents = DocumentSplitter::splitDocument($doc, 1000, "\n");
        $this->assertCount(12, $documents);
    }
}
