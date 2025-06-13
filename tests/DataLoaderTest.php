<?php

namespace NeuronAI\Tests;

use NeuronAI\RAG\DataLoader\StringDataLoader;
use PHPUnit\Framework\TestCase;

class DataLoaderTest extends TestCase
{
    public function test_string_data_loader()
    {
        $documents = StringDataLoader::for('test')->getDocuments();
        $this->assertCount(1, $documents);
        $this->assertEquals('test', $documents[0]->content);
        $this->assertEquals(\hash('sha256', 'test'), $documents[0]->hash);
    }
}
