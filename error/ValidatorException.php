<?php

namespace mgcode\graphql\error;

use Throwable;
use yii\base\Exception;
use yii\base\Model;

class ValidatorException extends Exception
{
    public $formatErrors = [];

    /**
     * ValidatorException constructor.
     * @param Model $model
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($model, $code = 0, Throwable $previous = null)
    {
        parent::__construct("{$model->formName()} validation failed", $code, $previous);
        $this->formatModelErrors($model);
    }

    /**
     * @param Model $model
     */
    private function formatModelErrors($model)
    {
        foreach ($model->getErrors() as $attribute => $messages) {
            $this->formatErrors[] = [
                'field' => $attribute,
                'messages' => $messages,
            ];
        }
    }
}