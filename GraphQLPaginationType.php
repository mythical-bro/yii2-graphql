<?php

namespace mgcode\graphql;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use yii\data\DataProviderInterface;

class GraphQLPaginationType extends ObjectType
{
    public function __construct(Type $type, $customName = null)
    {
        $name = $customName ?: $type->name.'Pagination';
        $config = [
            'name' => $name,
            'fields' => $this->getPaginationFields($type)
        ];
        parent::__construct($config);
    }

    protected function getPaginationFields(Type $type)
    {
        return [
            'data' => [
                'type' => Type::listOf($type),
                'description' => 'List of items on the current page',
                'resolve' => function (DataProviderInterface $data) {
                    return $data->getModels();
                },
            ],
            'total' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'Number of total items selected by the query',
                'resolve' => function (DataProviderInterface $data) {
                    return $data->getTotalCount();
                },
                'selectable' => false,
            ],
            'per_page' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'Number of items returned per page',
                'resolve' => function (DataProviderInterface $data) {
                    return $data->getPagination()->getPageSize();
                },
            ],
            'current_page' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'Current page of the cursor',
                'resolve' => function (DataProviderInterface $data) {
                    return $data->getPagination()->getPage() + 1;
                },
            ],
            'page_count' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'Current page of the cursor',
                'resolve' => function (DataProviderInterface $data) {
                    return $data->getPagination()->getPageCount();
                },
            ],
        ];
    }
}