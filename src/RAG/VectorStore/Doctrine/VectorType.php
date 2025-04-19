<?php

namespace NeuronAI\RAG\VectorStore\Doctrine;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class VectorType extends Type
{
    final public const VECTOR = 'vector';

    /**
     * @param  mixed[]  $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // getName is deprecated since doctrine/dbal 2.13 see: https://github.com/doctrine/dbal/issues/4749
        // BUT it is the most stable way to check if the platform is PostgreSQLPlatform in a lot of doctrine versions
        // so we will use it and add a check for the class name in case it is removed in the future
        if (method_exists($platform, 'getName') && ! \in_array($platform->getName(), SupportedDoctrineVectorStore::values())) {
            throw Exception::notSupported('VECTORs not supported by Platform.');
        }

        if (! isset($column['length'])) {
            throw Exception::notSupported('VECTORs must have a length.');
        }

        if ($column['length'] < 1) {
            throw Exception::notSupported('VECTORs must have a length greater than 0.');
        }

        if (! is_int($column['length'])) {
            throw Exception::notSupported('VECTORs must have a length that is an integer.');
        }

        return sprintf('vector(%d)', $column['length']);
    }

    /**
     * @return float[]
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if ($value === null) {
            return [];
        }

        $value = is_resource($value) ? stream_get_contents($value) : $value;

        if (! is_string($value)) {
            throw Exception::notSupported('Error while converting VECTORs to PHP value.');
        }

        $convertedValue = explode(',', $value);
        $floatArray = [];
        foreach ($convertedValue as $singleConvertedValue) {
            $floatArray[] = (float) $singleConvertedValue;
        }

        return $floatArray;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        //If $value is not a float array throw an exception
        if (! is_array($value)) {
            throw Exception::notSupported('VECTORs must be an array.');
        }

        return SupportedDoctrineVectorStore::fromPlatform($platform)->getVectorAsString($value);
    }

    public function convertToDatabaseValueSQL(mixed $sqlExpr, AbstractPlatform $platform): string
    {
        return SupportedDoctrineVectorStore::fromPlatform($platform)->convertToDatabaseValueSQL($sqlExpr);
    }

    public function canRequireSQLConversion(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return self::VECTOR;
    }
}
