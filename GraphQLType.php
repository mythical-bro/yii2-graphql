<?php

namespace mgcode\graphql;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use yii\base\BaseObject;

abstract class GraphQLType extends BaseObject
{
    /**
     * @return string Name must be unique across all system.
     */
    abstract public function name(): string;

    abstract public function fields(): array;

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
            'fields' => function () {
                return $this->getFields();
            }
        ];
        if (($description = $this->description()) !== null) {
            $attributes['description'] = $description;
        }
        return $attributes;
    }

    protected function getFields(): array
    {
        $fields = $this->fields();
        $allFields = [];
        foreach ($fields as $name => $field) {
            if ($field instanceof FieldDefinition) {
                $allFields[$field->name] = $field;
            } else {
                if ($field instanceof Type) {
                    $field = [
                        'type' => $field
                    ];
                }
                if ($resolver = $this->getFieldResolver($name, $field)) {
                    $field['resolve'] = $resolver;
                }
                $allFields[$name] = $field;
            }
        }
        return $allFields;
    }

    protected function getFieldResolver($name, $field)
    {
        if (isset($field['resolve'])) {
            return $field['resolve'];
        }
        $resolveMethod = 'resolve'.ucfirst($name);
        if (method_exists($this, $resolveMethod)) {
            $resolver = [$this, $resolveMethod];
            return function () use ($resolver) {
                $args = func_get_args();
                return call_user_func_array($resolver, $args);
            };
        }
        return null;
    }

    /**
     * @return ObjectType
     */
    public static function type(): ObjectType
    {
        static $type;
        if ($type === null) {
            $object = new static();
            $type = new ObjectType($object->toArray());
        }
        return $type;
    }
}