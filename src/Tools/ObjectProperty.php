<?php

namespace NeuronAI\Tools;

use NeuronAI\StructuredOutput\JsonSchema;

class ObjectProperty implements ToolPropertyInterface
{
    protected PropertyType $type = PropertyType::OBJECT;

    /**
     * @param string $name The name of the property.
     * @param string $description A description explaining the purpose or usage of the property.
     * @param bool $required Whether the property is required (true) or optional (false). Defaults to false.
     * @param string|null $class The associated class name, or null if not applicable.
     * @param array<ToolPropertyInterface> $properties An array of additional properties.
     */
    public function __construct(
        protected string  $name,
        protected string  $description,
        protected bool    $required = false,
        // QUESTION: should we prefer class mapping, programmatic definition or accepting both (actual) ?
        // At the end, the property has to fit the tool callable signature.
        // Too much flexibility could increase the complexity and amount of errors.
        protected ?string $class = null,
        protected array   $properties = [],
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'properties' => $this->getJsonSchema(),
            'required' => $this->required,
        ];
    }

    // The mapped class required properties and required properties are merged
    public function getRequiredProperties(): array
    {
        $required =  array_values(\array_filter(\array_map(function (ToolPropertyInterface $property) {
            return $property->isRequired() ? $property->getName() : null;
        }, $this->properties)));

        if (class_exists($this->getClass())) {
            $classSchema = (new JsonSchema())->generate($this->getClass());

            // In case of doublons, priority given to the properties
            foreach ($classSchema['required'] as $r) {
                if (!in_array($r, $required)) {
                    $required[] = $r;
                }
            }
        }

        return $required;
    }

    public function getJsonSchema(): array
    {
        $schema = [
            'type' => $this->type->value,
            'description' => $this->description,
        ];

        $properties = \array_reduce($this->properties, function (array $carry, ToolPropertyInterface $property) {
            $carry[$property->getName()] = $property->getJsonSchema();
            return $carry;
        }, []);

        // The mapped class properties and properties are merged
        if (class_exists($this->getClass())) {
            $classSchema = (new JsonSchema())->generate($this->getClass());

            foreach ($classSchema['properties'] as $name => $meta) {
                // In case of doublons, priority given to the properties
                if (!isset($properties[$name])) {
                    $properties[$name] = [
                        'type' => $meta['type'],
                        'description' => $meta['description'],
                    ];
                }
            }
        }

        if (!empty($properties)) {
            $schema['properties'] = $properties;
            $schema['required'] = $this->getRequiredProperties();
        }

        return $schema;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): PropertyType
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }
}
