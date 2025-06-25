<?php

declare(strict_types=1);

namespace NeuronAI;

trait StaticConstructor
{
    /**
     * Static constructor.
     */
    public static function make(...$arguments): static
    {
        /** @phpstan-ignore new.static */
        return new static(...$arguments);
    }
}
