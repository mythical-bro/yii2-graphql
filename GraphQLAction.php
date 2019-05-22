<?php

namespace mgcode\graphql;

use GraphQL\Error\Debug;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Executor;
use GraphQL\Experimental\Executor\CoroutineExecutor;
use GraphQL\GraphQL;
use GraphQL\Server\RequestError;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use mgcode\graphql\error\ValidatorException;
use mgcode\helpers\ArrayHelper;
use yii\base\Action;
use yii\base\InvalidArgumentException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class GraphQLAction extends Action
{
    public $queries = [];
    public $mutations = [];

    /** @inheritdoc */
    public function init()
    {
        parent::init();
        $this->controller->enableCsrfValidation = false;
        \Yii::$app->response->format = Response::FORMAT_JSON;
        \Yii::$app->request->parsers = [
            'application/json' => \yii\web\JsonParser::class,
        ];
        Executor::setImplementationFactory([CoroutineExecutor::class, 'create']);
    }

    public function run()
    {
        $params = $this->parseParameters();
        if (!isset($params['query']) && isset($params[0]['query'])) {
            throw new Error('Query batching is not supported.');
        }

        $query = ArrayHelper::getValue($params, 'query');
        $variables = ArrayHelper::getValue($params, 'variables');
        if (is_string($variables)) {
            $variables = json_decode($variables, true);
        }
        $operationName = ArrayHelper::getValue($params, 'operationName');

        // Create schema
        $schema = new Schema([
            'query' => $this->createObject('Query', $this->queries),
            'mutation' => !empty($this->mutations) ? $this->createObject('Mutation', $this->mutations) : null,
        ]);

        // Disable scheme introspection
        if (!$this->getDebug()) {
            DocumentValidator::addRule(new DisableIntrospection());
        }

        $result = GraphQL::executeQuery(
            $schema,
            $query,
            null,
            null,
            $variables,
            $operationName
        );
        $result->setErrorFormatter([$this, 'formatError']);
        $result->setErrorsHandler([$this, 'handleErrors']);

        // Return result
        return $result->toArray($this->getDebug());
    }

    protected function createObject($name, array $fields = []): ObjectType
    {
        $config = [
            'name' => $name,
            'fields' => function () use ($fields) {
                $result = [];
                foreach ($fields as $name => $field) {
                    $result[$name] = static::composeFields($field);
                }
                return $result;
            }
        ];
        return new ObjectType($config);
    }

    protected function composeFields($field): array
    {
        if (is_string($field)) {
            $field = new $field();
            if ($field instanceof GraphQLField) {
                return $field->toArray();
            }
        }
        if ($field instanceof GraphQLField) {
            return $field->toArray();
        } else if (is_array($field)) {
            return $field;
        }
        throw new InvalidArgumentException();
    }

    /**
     * Parses request parameters
     * @return array
     */
    protected function parseParameters(): array
    {
        $request = \Yii::$app->request;
        if (!($params = $request->post())) {
            $params = $request->get();
        }

        $contentType = $request->getHeaders()->get('content-type', '');
        if (mb_stripos($contentType, 'multipart/form-data') !== false) {
            $this->validateParsedBody($params);
            return $this->parseMultipartParameters($params);
        }
        return $params;
    }

    protected function validateParsedBody($params)
    {
        if (null === $params) {
            throw new InvariantViolation(
                'Request is expected to provide parsed body for "multipart/form-data" requests but got null'
            );
        }

        if (!is_array($params)) {
            throw new RequestError(
                'GraphQL Server expects JSON object or array, but got '.Utils::printSafe($params)
            );
        }

        if (empty($params)) {
            throw new InvariantViolation(
                'Request is expected to provide parsed body for "multipart/form-data" requests but got empty array'
            );
        }

        if (!isset($params['map'])) {
            throw new RequestError('The request must define a `map`');
        }
    }

    protected function parseMultipartParameters($params)
    {
        $map = json_decode($params['map'], true);
        $result = json_decode($params['operations'], true);

        foreach ($map as $fileKey => $locations) {
            foreach ($locations as $location) {
                $items = &$result;
                foreach (explode('.', $location) as $key) {
                    if (!isset($items[$key]) || !is_array($items[$key])) {
                        $items[$key] = [];
                    }
                    $items = &$items[$key];
                }

                $file = $_FILES[$fileKey];
                $items = isset($file['name']) ? $this->createFileInstance($file) : array_map([$this, 'createFileInstance'], $file);
            }
        }
        return $result;
    }

    protected function createFileInstance($file): UploadedFile
    {
        return new UploadedFile([
            'name' => $file['name'],
            'tempName' => $file['tmp_name'],
            'type' => $file['type'],
            'size' => $file['size'],
            'error' => $file['error'],
        ]);
    }

    protected function getDebug()
    {
        $debug = false;
        if (YII_ENV_DEV || YII_ENV_TEST) {
            $debug = Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE;
        }
        return $debug;
    }

    public function formatError(Error $e)
    {
        $formatter = FormattedError::prepareFormatter(null, $this->getDebug());
        $error = $formatter($e);
        $previous = $e->getPrevious();

        if ($previous) {
            if ($previous instanceof ValidatorException) {
                $error = [
                        'validation' => $previous->formatErrors,
                        'message' => $previous->getMessage(),
                    ] + $error;
            } else if ($previous instanceof HttpException) {
                $error = [
                        'statusCode' => $previous->statusCode,
                        'message' => $previous->getMessage(),
                    ] + $error;
            }
        }
        return $error;
    }

    public function handleErrors(array $errors, callable $formatter)
    {
        foreach ($errors as $error) {
            // Try to unwrap exception
            $error = $error->getPrevious() ?: $error;
            // Don't report certain GraphQL errors
            if ($error instanceof ValidatorException || $error instanceof HttpException) {
                continue;
            }
            \Yii::$app->errorHandler->logException($error);
        }
        return array_map($formatter, $errors);
    }
}