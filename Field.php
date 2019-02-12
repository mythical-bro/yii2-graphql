<?php

namespace mgcode\graphql;

use GraphQL\Type\Definition\Type;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;

abstract class Field extends BaseObject
{
    abstract public function description(): string;

    abstract public function type(): Type;

    public function attributes(): array
    {
        return [];
    }

    public function args(): array
    {
        return [];
    }

    /**
     * Convert instance to an array.
     * @return array
     */
    public function toArray(): array
    {
        if (!method_exists($this, 'resolve')) {
            throw new InvalidConfigException('Resolve callback is not defined');
        }
        $attributes = array_merge([
            'description' => $this->description(),
            'type' => $this->type(),
            'args' => $this->args(),
            'resolve' => [$this, 'resolve'],
        ], $this->attributes());
        return $attributes;
    }
}