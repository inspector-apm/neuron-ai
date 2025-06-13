<?php

namespace Tests\RAG\Splitter;

use NeuronAI\RAG\Document;
use NeuronAI\RAG\Splitter\DelimiterTextSplitter;
use PHPUnit\Framework\TestCase;

class DelimiterTextSplitterTest extends TestCase
{
    public function test_split_long_text()
    {
        $doc = new Document(file_get_contents(__DIR__.'/../stubs/long-text.txt'));

        $splitter = new DelimiterTextSplitter();
        $documents = $splitter->splitDocument($doc);
        $this->assertCount(7, $documents);

        $splitter = new DelimiterTextSplitter(maxLength: 500);
        $documents = $splitter->splitDocument($doc);
        $this->assertCount(14, $documents);

        $splitter = new DelimiterTextSplitter(maxLength: 1000, separator: "\n");
        $documents = $splitter->splitDocument($doc);
        $this->assertCount(12, $documents);
    }
}
