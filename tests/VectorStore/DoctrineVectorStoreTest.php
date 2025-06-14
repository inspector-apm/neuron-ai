<?php

namespace NeuronAI\Tests\VectorStore;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\VectorStore\Doctrine\DoctrineVectorStore;
use NeuronAI\RAG\VectorStore\Doctrine\VectorType;
use NeuronAI\Tests\Traits\CheckOpenPort;
use NeuronAI\Tests\stubs\EntityVectorStub;
use PHPUnit\Framework\TestCase;

class DoctrineVectorStoreTest extends TestCase
{
    use CheckOpenPort;

    private const TYPE_NAME = 'vector';
    private const EMBEDDING_SIZE = 3072;
    private EntityManager $entityManager;
    private SchemaTool $schemaTool;
    private ClassMetadata $metadata;
    private array $embeddingToSearch;

    protected function setUp(): void
    {
        if (!$this->isPortOpen('127.0.0.1', 5432)) {
            $this->markTestSkipped('Port 3306 is not open. Skipping test.');
        }
        try {
            $this->bootstrapDatabase();
        } catch (\PDOException $e) {
            $this->markTestSkipped("❌ Connection or database creation error: " . $e->getMessage());
        }

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
            'user'     => 'postgres',
            'password' => 'postgres',
            'host'     => '127.0.0.1',
            'port'     => 5432,
            'driver'   => 'pdo_pgsql',
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

    protected function bootstrapDatabase()
    {
        $scriptPath = __DIR__ . '/../scripts/setup-test-db.php';

        if (!file_exists($scriptPath)) {
            throw new \RuntimeException("Bootstrap script not found at $scriptPath");
        }

        require_once $scriptPath;
    }

    /**
     * @dataProvider provideVectors
     */
    public function test_add_documents_and_search(array $embeddings, array $expectedEmbeddings): void
    {
        $documents = [];
        foreach ($embeddings as $embedding) {
            $document = new EntityVectorStub();
            $document->embedding = $embedding;
            $documents[] = $document;
        }

        $vectorStore = new DoctrineVectorStore($this->entityManager, EntityVectorStub::class);
        $vectorStore->addDocuments($documents);

        $entitiesVectorStub = $vectorStore->similaritySearch($this->embeddingToSearch, Document::class);

        $this->assertCount(2, $entitiesVectorStub);
        foreach ($entitiesVectorStub as $index => $entityVectorStub) {
            $this->assertEquals($expectedEmbeddings[$index], $entityVectorStub->getEmbedding());
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
