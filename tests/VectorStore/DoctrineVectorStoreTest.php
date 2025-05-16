<?php

namespace NeuronAI\Tests\VectorStore;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use NeuronAI\RAG\VectorStore\Doctrine\DoctrineVectorStore;
use NeuronAI\RAG\VectorStore\Doctrine\VectorType;
use NeuronAI\Tests\stubs\EntityVectorStub;
use NeuronAI\Tests\Traits\NeedsDatabaseBootstrap;
use PHPUnit\Framework\TestCase;

class DoctrineVectorStoreTest extends TestCase
{
    private const TYPE_NAME = 'vector';
    private const EMBEDDING_SIZE = 3072;
    private EntityManager $entityManager;
    private SchemaTool $schemaTool;
    private ClassMetadata $metadata;

    use NeedsDatabaseBootstrap;

    protected function setUp(): void
    {
        if (!Type::hasType(self::TYPE_NAME)) {
            Type::addType(self::TYPE_NAME, VectorType::class);
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../../src/RAG'],
            isDevMode: true,
            reportFieldsWhereDeclared: true,
        );

        $connection = DriverManager::getConnection([
            'dbname'   => 'neuron_ai_test',
            'user'     => 'root',
            'password' => '',
            'host'     => '127.0.0.1',
            'port'     => 3306,
            'driver'   => 'pdo_mysql',
        ], $config);

        $platform = $connection->getDatabasePlatform();
        if (!$platform->hasDoctrineTypeMappingFor('vector')) {
            $platform->registerDoctrineTypeMapping('vector', 'vector');
        }

        $this->entityManager = new EntityManager($connection, $config);
        $this->schemaTool = new SchemaTool($this->entityManager);
        $this->metadata = $this->entityManager->getClassMetadata(EntityVectorStub::class);
        $this->schemaTool->createSchema([$this->metadata]);
        $this->embeddingToSearch = json_decode(file_get_contents(__DIR__ . '/../stubs/hello-world.embeddings'), true);
    }

    /**
     * @dataProvider provideVectors
     */
    public function test_add_documents_and_search(array $embeddings, array $expectedEmbeddings): void {
        $documents = [];
        foreach ($embeddings as $embedding) {
            $document = new EntityVectorStub();
            $document->embedding = $embedding;
            $documents[] = $document;
        }

        $vectorStore = new DoctrineVectorStore($this->entityManager, EntityVectorStub::class);
        $vectorStore->addDocuments($documents);

        $entitiesVectorStub = $vectorStore->similaritySearch($this->embeddingToSearch, 2);

        $this->assertCount(2, $entitiesVectorStub);
        foreach ($entitiesVectorStub as $index => $entityVectorStub) {
            $this->assertEquals($expectedEmbeddings[$index], $entityVectorStub->embedding);
        }
    }

    public function provideVectors(): array
    {
        return [
            [
                [
                    $this->generateVectors([0.512, -0.341, 0.123]),
                    $this->generateVectors([-0.278, 0.659, -0.487]),
                    $this->generateVectors([0.194, -0.055, 0.732]),
                ],
                [
                    $this->generateVectors([0.512, -0.341, 0.123]),
                    $this->generateVectors([-0.278, 0.659, -0.487]),
                    $this->generateVectors([0.194, -0.055, 0.732]),
                ]
            ],
        ];
    }

    private function generateVectors(array $vectors): array
    {
        $result = [];
        $count = count($vectors);

        for ($i = 0; $i < self::EMBEDDING_SIZE; $i++) {
            $result[] = $vectors[$i % $count];
        }

        return $result;
    }

    protected function tearDown(): void
    {
        $this->schemaTool->dropSchema([$this->metadata]);
    }
}
