<?php

namespace mgcode\graphql;

use GraphQL\Type\Definition\EnumType;
use yii\base\BaseObject;

abstract class GraphQLEnumType extends BaseObject
{
    /**
     * @return string Name must be unique across all system.
     */
    abstract public function name(): string;

    /**
     * See http://webonyx.github.io/graphql-php/type-system/enum-types/ for configuration.
     * @return array
     */
    abstract public function values(): array;

    public function description(): ?string
    {
        return null;
    }

    /**
     * Convert instance to an array.
     * @return array
     */
    public function toArray(): array
    {
        $attributes = [
            'name' => $this->name(),
            'values' => $this->values(),
        ];
        if (($description = $this->description()) !== null) {
            $attributes['description'] = $description;
        }
        return $attributes;
    }

    /**
     * @return EnumType
     */
    public static function type(): EnumType
    {
        static $type;
        if ($type === null) {
            $object = new static();
            $type = new EnumType($object->toArray());
        }
        return $type;
    }
}