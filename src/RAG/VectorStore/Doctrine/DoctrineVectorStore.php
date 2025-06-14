<?php

namespace NeuronAI\RAG\VectorStore\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\DocumentModelInterface;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;

class DoctrineVectorStore implements VectorStoreInterface
{
    protected array $filters = [];

    private readonly SupportedDoctrineVectorStore $doctrineVectorStoreType;
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        public readonly string $entityClassName,
        protected int $topK = 4,
    ) {
        if (!interface_exists(EntityManagerInterface::class)) {
            throw new \RuntimeException('To use this functionality, you must install the `doctrine/orm` package: `composer require doctrine/orm`.');
        }

        $conn = $entityManager->getConnection();
        $this->doctrineVectorStoreType = SupportedDoctrineVectorStore::fromPlatform($conn->getDatabasePlatform());
        $registeredTypes = Type::getTypesMap();
        if (!array_key_exists(VectorType::VECTOR, $registeredTypes)) {
            Type::addType(VectorType::VECTOR, VectorType::class);
            $conn->getDatabasePlatform()->registerDoctrineTypeMapping('vector', VectorType::VECTOR);
        }

        $this->doctrineVectorStoreType->addCustomisationsTo($this->entityManager);
    }

    public function addDocument(DocumentModelInterface $document): void
    {
        if (empty($document->embedding)) {
            throw new \RuntimeException('document embedding must be set before adding a document');
        }

        $this->persistDocument($document);
        $this->entityManager->flush();
    }

    public function addDocuments(array $documents): void
    {
        if ($documents === []) {
            return;
        }
        foreach ($documents as $document) {
            $this->persistDocument($document);
        }

        $this->entityManager->flush();
    }

    public function similaritySearch(array $embedding, string $documentModel): array
    {
        $repository = $this->entityManager->getRepository($this->entityClassName);

        $qb = $repository
            ->createQueryBuilder('e')
            ->orderBy($this->doctrineVectorStoreType->l2DistanceName().'(e.embedding, :embeddingString)', 'ASC')
            ->setParameter('embeddingString', $this->doctrineVectorStoreType->getVectorAsString($embedding))
            ->setMaxResults($this->topK);

        foreach ($this->filters as $key => $value) {
            $paramName = 'where_'.$key;
            $qb->andWhere(sprintf('e.%s = :%s', $key, $paramName))
                ->setParameter($paramName, $value);
        }

        /** @var DoctrineEmbeddingEntityBase[] */
        return $qb->getQuery()->getResult();
    }

    private function persistDocument(DocumentModelInterface $document): void
    {
        if (empty($document->embedding)) {
            throw new \RuntimeException('Trying to save a document in a vectorStore without embedding');
        }

        if (!$document instanceof DoctrineEmbeddingEntityBase) {
            throw new \RuntimeException('Document needs to be an instance of DoctrineEmbeddingEntityBase');
        }

        $this->entityManager->persist($document);
    }

    /**
     * @return iterable<Document>
     */
    public function fetchDocumentsByChunkRange(string $sourceType, string $sourceName, int $leftIndex, int $rightIndex): iterable
    {
        $repository = $this->entityManager->getRepository($this->entityClassName);

        $query = $repository->createQueryBuilder('d')
            ->andWhere('d.sourceType = :sourceType')
            ->andWhere('d.sourceName = :sourceName')
            ->andWhere('d.chunkNumber >= :lower')
            ->andWhere('d.chunkNumber <= :upper')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('sourceName', $sourceName)
            ->setParameter('lower', $leftIndex)
            ->setParameter('upper', $rightIndex)
            ->getQuery();

        return $query->toIterable();
    }

    public function withFilters(array $filters): VectorStoreInterface
    {
        $this->filters = $filters;
        return $this;
    }
}
