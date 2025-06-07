<?php

namespace NeuronAI\Tests\stubs;

use NeuronAI\StructuredOutput\SchemaProperty;

class Color
{
    public function __construct(
        #[SchemaProperty(description: "The RED", required: true)]
        public float $r,
        #[SchemaProperty(description: "The GREEN", required: true)]
        public float $g,
        #[SchemaProperty(description: "The BLUE", required: true)]
        public float $b,
    ) {
    }


}
