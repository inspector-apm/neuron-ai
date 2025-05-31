<?php

namespace NeuronAI\Properties;

interface PropertyInterface extends \JsonSerializable
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return string|array
     */
    public function getType(): string|array;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @return bool
     */
    public function isRequired(): bool;
}
