<?php

namespace mgcode\graphql;

use GraphQL\Type\Definition\ObjectType;
use yii\base\InvalidArgumentException;

class MutationType extends ObjectType
{
    public static function create(array $mutations = [])
    {
        $config = [
            'fields' => function () use ($mutations) {
                $result = [];
                foreach ($mutations as $name => $mutation) {
                    $result[$name] = static::composeMutation($mutation);
                }
                return $result;
            }
        ];
        return new static($config);
    }

    protected static function composeMutation($mutation): array
    {
        if (is_array($mutation)) {
            return $mutation;
        }
        throw new InvalidArgumentException();
    }
}