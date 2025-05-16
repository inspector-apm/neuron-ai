<?php

namespace NeuronAI\Tests\stubs;

use Doctrine\ORM\Mapping as ORM;
use NeuronAI\RAG\VectorStore\Doctrine\DoctrineEmbeddingEntityBase;

#[ORM\Entity]
#[ORM\Table(name: 'entity_vector_stub')]
class EntityVectorStub extends DoctrineEmbeddingEntityBase
{
}
