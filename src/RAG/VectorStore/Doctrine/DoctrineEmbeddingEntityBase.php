<?php

namespace NeuronAI\RAG\VectorStore\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use NeuronAI\RAG\Document;

class DoctrineEmbeddingEntityBase extends Document
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public mixed $id;

    // The length of the vector is 1536 by default, but you should override this in your own entity.
    #[ORM\Column(type: VectorType::VECTOR, length: 1536)]
    public ?array $embedding;

    #[ORM\Column(type: Types::TEXT)]
    public string $content;

    #[ORM\Column(type: Types::TEXT)]
    public string $sourceType = 'manual';

    #[ORM\Column(type: Types::TEXT)]
    public string $sourceName = 'manual';

    #[ORM\Column(type: Types::INTEGER)]
    public int $chunkNumber = 0;

    public function getId(): int
    {
        return $this->id;
    }
}
