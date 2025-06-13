<?php

namespace NeuronAI\RAG\DataLoader;

use NeuronAI\RAG\Splitter\DelimiterTextSplitter;
use NeuronAI\RAG\Splitter\SplitterInterface;

abstract class AbstractDataLoader implements DataLoaderInterface
{
    protected SplitterInterface $splitter;

    public function __construct()
    {
        $this->splitter = new DelimiterTextSplitter(
            maxLength: 1000,
            separator: '.',
            wordOverlap: 0
        );
    }

    public static function for(...$arguments): static
    {
        /** @phpstan-ignore new.static */
        return new static(...$arguments);
    }

    public function withSplitter(SplitterInterface $splitter): DataLoaderInterface
    {
        $this->splitter = $splitter;
        return $this;
    }
}
