<?php

namespace NeuronAI\RAG\VectorStore\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\EntityManagerInterface;

abstract class SupportedDoctrineVectorStore
{
    /**
     * @param  float[]  $vector
     */
    abstract public function getVectorAsString(array $vector): string;

    abstract public function convertToDatabaseValueSQL(string $sqlExpr): string;

    abstract public function addCustomisationsTo(EntityManagerInterface $entityManager): void;

    abstract public function l2DistanceName(): string;

    /**
     * @param  float[]  $vector
     */
    protected function stringListOf(array $vector): string
    {
        return \implode(',', $vector);
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return [
            'postgresql',
            'mysql',
        ];
    }

    public static function fromPlatform(AbstractPlatform $platform): self
    {
        if (str_starts_with($platform::class, 'Doctrine\DBAL\Platforms\MariaDb')) {
            return new MariaDBVectorStoreType();
        }
        if (str_starts_with($platform::class, 'Doctrine\DBAL\Platforms\PostgreSQL')) {
            return new PostgresqlVectorStoreType();
        }

        throw new \RuntimeException('Unsupported DoctrineVectorStore type');
    }
}
