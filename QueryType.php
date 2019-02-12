<?php

namespace mgcode\graphql;

use GraphQL\Type\Definition\ObjectType;
use yii\base\InvalidArgumentException;

class QueryType extends ObjectType
{
    public static function create(array $queries = [])
    {
        $config = [
            'fields' => function () use ($queries) {
                $result = [];
                foreach ($queries as $name => $query) {
                    $result[$name] = static::composeQuery($query);
                }
                return $result;
            }
        ];
        return new static($config);
    }

    protected static function composeQuery($query): array
    {
        if (is_array($query)) {
            return $query;
        }
        throw new InvalidArgumentException();
    }
}