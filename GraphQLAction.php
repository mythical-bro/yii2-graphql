<?php

namespace mgcode\graphql;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils;
use GraphQL\Validator\DocumentValidator;
use mgcode\helpers\ArrayHelper;
use yii\base\Action;
use yii\base\InvalidArgumentException;
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
            'application/json' => 'yii\web\JsonParser',
        ];
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

        /** @var \GraphQL\Validator\Rules\QueryComplexity $queryComplexity */
        $queryComplexity = DocumentValidator::getRule('QueryComplexity');
        $queryComplexity->setMaxQueryComplexity($maxQueryComplexity = 1000);

        return GraphQL::execute(
            $schema,
            $query,
            null,
            null,
            $variables,
            $operationName
        );
    }

    protected function createObject($name, array $fields = [])
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

    protected function composeFields($field)
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
    protected function parseParameters()
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
            throw new InvalidArgumentException(
                'GraphQL Server expects JSON object or array, but got '.Utils::printSafe($params)
            );
        }

        if (empty($params)) {
            throw new InvariantViolation(
                'Request is expected to provide parsed body for "multipart/form-data" requests but got empty array'
            );
        }

        if (!isset($params['map'])) {
            throw new InvalidArgumentException('The request must define a `map`');
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

    protected function createFileInstance($file)
    {
        return new UploadedFile([
            'name' => $file['name'],
            'tempName' => $file['tmp_name'],
            'type' => $file['type'],
            'size' => $file['size'],
            'error' => $file['error'],
        ]);
    }
}