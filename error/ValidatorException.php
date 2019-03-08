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
    public static function custom(array $errors): self
    {
        $message = "Mutation validation failed.";
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
    public static function fromModel(Model $model): self
    {
        $errors = [];
        foreach ($model->getErrors() as $attribute => $messages) {
            $errors = [
                'field' => $attribute,
                'messages' => $messages,
            ];
        }
        $message = "{$model->formName()} validation failed.";
        $exception = new static($message);
        $exception->formatErrors = $errors;
        return $exception;
    }
}