<?php

namespace NeuronAI\RAG\VectorStore\Doctrine;

use Doctrine\ORM\Query\SqlWalker;

/**
 * L2DistanceFunction ::= "VEC_DISTANCE_EUCLIDEAN" "(" VectorPrimary "," VectorPrimary ")"
 */
final class MariaDBVectorL2OperatorDql extends AbstractDBL2OperatorDql
{
    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'VEC_DISTANCE_EUCLIDEAN('.
            $this->vectorOne->dispatch($sqlWalker).', '.
            'VEC_FROMTEXT('.
            $this->vectorTwo->dispatch($sqlWalker).
            ')'.
            ')';
    }
}

