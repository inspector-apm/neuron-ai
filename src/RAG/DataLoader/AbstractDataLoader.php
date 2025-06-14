<?php

namespace NeuronAI\RAG\DataLoader;

use NeuronAI\RAG\Document;

abstract class AbstractDataLoader implements DataLoaderInterface
{
    /**
     * The default document model.
     */
    protected string $documentModel = Document::class;

    protected int $maxLength = 1000;
    protected string $separator = '.';
    protected int $wordOverlap = 0;

    public static function for(...$arguments): static
    {
        /** @phpstan-ignore new.static */
        return new static(...$arguments);
    }

    public function withMaxLength(int $maxLength): DataLoaderInterface
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    public function withSeparator(string $separator): DataLoaderInterface
    {
        $this->separator = $separator;
        return $this;
    }

    public function withOverlap(int $overlap): DataLoaderInterface
    {
        $this->wordOverlap = $overlap;
        return $this;
    }

    public function withDocumentModel(string $model): DataLoaderInterface
    {
        $this->documentModel = $model;
        return $this;
    }
}
