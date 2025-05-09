<?php

namespace NeuronAI\Tests;

use NeuronAI\Tests\Utils\Address;
use NeuronAI\Tests\Utils\Person;
use NeuronAI\Tests\Utils\PersonWithAddress;
use NeuronAI\Tests\Utils\PersonWithTags;
use NeuronAI\Tests\Utils\Tag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

class ValidatorTest extends TestCase
{
    public function test_valid(): void
    {
        $obj = new Person();
        $obj->firstName = 'John';
        $obj->lastName = 'Doe';

        $loader = class_exists(AttributeLoader::class) ? new AttributeLoader() : new AnnotationLoader();
        $violations = Validation::createValidatorBuilder()
            ->addLoader($loader)
            ->getValidator()
            ->validate($obj);

        $this->assertEquals(0, $violations->count());
    }

    public function test_not_valid(): void
    {
        $obj = new Person();
        $obj->lastName = 'Doe';

        $loader = class_exists(AttributeLoader::class) ? new AttributeLoader() : new AnnotationLoader();
        $violations = Validation::createValidatorBuilder()
            ->addLoader($loader)
            ->getValidator()
            ->validate($obj);

        $this->assertEquals(1, $violations->count());
        $this->assertEquals('firstName', $violations->get(0)->getPropertyPath());

        foreach ($violations as $violation) {
            $this->assertInstanceOf(ConstraintViolation::class, $violation);
        }
    }

    public function test_validate_nested_class(): void
    {
        $obj = new PersonWithAddress();
        $obj->firstName = 'John';
        $obj->lastName = 'Doe';
        $obj->address = new Address();
        $obj->address->city = 'Rome';

        $loader = class_exists(AttributeLoader::class) ? new AttributeLoader() : new AnnotationLoader();
        $violations = Validation::createValidatorBuilder()
            ->addLoader($loader)
            ->getValidator()
            ->validate($obj);

        $this->assertEquals(2, $violations->count());
        $this->assertEquals('address.street', $violations->get(0)->getPropertyPath());
        $this->assertEquals('address.zip', $violations->get(1)->getPropertyPath());

        foreach ($violations as $violation) {
            $this->assertInstanceOf(ConstraintViolation::class, $violation);
        }
    }

    public function test_validate_array(): void
    {
        $obj = new PersonWithTags();
        $obj->firstName = 'John';
        $obj->lastName = 'Doe';

        $tag = new Tag();
        $tag->name = 'agent';
        $obj->tags = [$tag];

        $loader = class_exists(AttributeLoader::class) ? new AttributeLoader() : new AnnotationLoader();
        $violations = Validation::createValidatorBuilder()
            ->addLoader($loader)
            ->getValidator()
            ->validate($obj);

        $this->assertEquals(0, $violations->count());
    }

    public function test_array_not_valid(): void
    {
        $obj = new PersonWithTags();
        $obj->firstName = 'John';
        $obj->lastName = 'Doe';

        $loader = class_exists(AttributeLoader::class) ? new AttributeLoader() : new AnnotationLoader();
        $violations = Validation::createValidatorBuilder()
            ->addLoader($loader)
            ->getValidator()
            ->validate($obj);

        $this->assertEquals(1, $violations->count());

        foreach ($violations as $violation) {
            $this->assertInstanceOf(ConstraintViolation::class, $violation);
        }
    }
}
