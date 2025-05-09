<?php

namespace NeuronAI\RAG\VectorStore\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use NeuronAI\RAG\Document;

#[ORM\MappedSuperclass]
abstract class DoctrineEmbeddingEntityBase extends Document
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public mixed $id;

    #[ORM\Column(type: VectorType::VECTOR, length: 3072)]
    public ?array $embedding;

    #[ORM\Column(type: Types::TEXT)]
    public string $content;

    #[ORM\Column(type: Types::TEXT)]
    public string $sourceType = 'manual';

    #[ORM\Column(type: Types::TEXT)]
    public string $sourceName = 'manual';

    #[ORM\Column(type: Types::INTEGER)]
    public int $chunkNumber = 0;

    public function getId(): ?string
    {
        return $this->id;
    }
}
