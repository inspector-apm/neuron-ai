<?php

namespace NeuronAI\RAG\VectorStore\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;

abstract class AbstractDBL2OperatorDql extends FunctionNode
{
    protected Node $vectorTwo;

    protected Node $vectorOne;

    public function parse(Parser $parser): void
    {
        if (class_exists(\Doctrine\ORM\Query\TokenType::class)) {
            $parser->match(\Doctrine\ORM\Query\TokenType::T_IDENTIFIER);
            $parser->match(\Doctrine\ORM\Query\TokenType::T_OPEN_PARENTHESIS);
        } else {
            $parser->match(\Doctrine\ORM\Query\Lexer::T_IDENTIFIER);
            $parser->match(\Doctrine\ORM\Query\Lexer::T_OPEN_PARENTHESIS);
        }

        $this->vectorOne = $parser->ArithmeticFactor(); // Fix that, should be vector

        if (class_exists(\Doctrine\ORM\Query\TokenType::class)) {
            $parser->match(\Doctrine\ORM\Query\TokenType::T_COMMA);
        } else {
            $parser->match(\Doctrine\ORM\Query\Lexer::T_COMMA);
        }

        $this->vectorTwo = $parser->ArithmeticFactor(); // Fix that, should be vector

        if (class_exists(\Doctrine\ORM\Query\TokenType::class)) {
            $parser->match(\Doctrine\ORM\Query\TokenType::T_CLOSE_PARENTHESIS);
        } else {
            $parser->match(\Doctrine\ORM\Query\Lexer::T_CLOSE_PARENTHESIS);
        }
    }
}
