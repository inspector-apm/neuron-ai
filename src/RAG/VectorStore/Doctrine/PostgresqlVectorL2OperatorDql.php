<?php

namespace NeuronAI\RAG\VectorStore\Doctrine;

use Doctrine\ORM\Query\SqlWalker;

/**
 * L2DistanceFunction ::= "L2_DISTANCE" "(" VectorPrimary "," VectorPrimary ")"
 */
final class PostgresqlVectorL2OperatorDql extends AbstractDBL2OperatorDql
{
    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'L2_DISTANCE('.
            $this->vectorOne->dispatch($sqlWalker).', '.
            $this->vectorTwo->dispatch($sqlWalker).
            ')';
    }
}
