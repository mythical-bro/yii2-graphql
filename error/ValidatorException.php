<?php

namespace mgcode\graphql\error;

use Throwable;
use yii\base\Exception;
use yii\base\Model;

class ValidatorException extends Exception
{
    public $formatErrors = [];

    /**
     * Creates custom validation error
     * @param array $errors
     * @return ValidatorException
     */
    public static function custom(array $errors)
    {
        $message = "Mutation validation failed.";
        $exception = new static($message);
        $exception->formatErrors = $errors;
        return $exception;
    }

    /**
     * Validation error from attributes
     * @param array $attributes
     * @return ValidatorException
     */
    public static function fromAttributes(array $attributes)
    {
        $errors = [];
        foreach ($attributes as $attribute => $messages) {
            if (!is_array($messages)) {
                $messages = [$messages];
            }
            $errors[] = [
                'field' => $attribute,
                'messages' => $messages,
            ];
        }

        $message = "Validation failed.";
        $exception = new static($message);
        $exception->formatErrors = $errors;
        return $exception;
    }

    /**
     * Validation error from Model
     * @param Model $model
     * @return ValidatorException
     * @throws \yii\base\InvalidConfigException
     */
    public static function fromModel(Model $model)
    {
        return static::fromAttributes($model->getErrors());
    }
}