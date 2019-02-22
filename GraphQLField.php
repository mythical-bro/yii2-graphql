<?php

namespace mgcode\graphql;

use GraphQL\Type\Definition\Type;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;

abstract class GraphQLField extends BaseObject
{
    /** @var string|null Used for custom fields */
    public $name;

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

    protected function getResolver()
    {
        if (!method_exists($this, 'resolve')) {
            return null;
        }
        $resolver = [$this, 'resolve'];
        $authorize = [$this, 'authorize'];

        return function () use ($resolver, $authorize) {
            $arguments = func_get_args();
            // Authorize request
            if (method_exists($this, 'authorize') && call_user_func_array($authorize, $arguments) !== true) {
                throw new ForbiddenHttpException('You are not allowed to perform this action.');
            }
            return call_user_func_array($resolver, $arguments);
        };
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
        ], $this->attributes());

        if ($resolver = $this->getResolver()) {
            $attributes['resolve'] = $resolver;
        }
        return $attributes;
    }
}