<?php

namespace mgcode\graphql;

use GraphQL\GraphQL;
use GraphQL\Error\Debug;
use GraphQL\Executor\Executor;
use GraphQL\Experimental\Executor\CoroutineExecutor;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use mgcode\graphql\error\ValidatorException;
use yii\base\Action;
use yii\web\HttpException;
use yii\web\Response;
use yii\base\InvalidArgumentException;
use yii\helpers\Json;

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
        Executor::setImplementationFactory([CoroutineExecutor::class, 'create']);
    }

    public function run()
    {
        list($query, $variables, $operation) = $this->parseParameters();

        // Create schema
        $schema = new Schema([
            'query' => $this->createObject('Query', $this->queries),
            'mutation' => !empty($this->mutations) ? $this->createObject('Mutation', $this->mutations) : null,
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            $query,
            null,
            null,
            empty($variables) ? null : $variables,
            empty($operation) ? null : $operation
        );
        $result->setErrorFormatter([$this, 'formatError']);

        // Log error
        foreach ($result->errors as $error) {
            $previous = $error->getPrevious();
            if ($previous) {
                \Yii::$app->errorHandler->logException($previous);
            }
        }

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
    protected function parseParameters()
    {
        // Parse parameters using MULTIPART, POST or GET
        $query = \Yii::$app->request->get('query', \Yii::$app->request->post('query'));
        $variables = \Yii::$app->request->get('variables', \Yii::$app->request->post('variables'));
        $operation = \Yii::$app->request->get('operation', \Yii::$app->request->post('operation', null));
        if (empty($query)) {
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
            $query = $input['query'];
            $variables = isset($input['variables']) ? $input['variables'] : [];
            $operation = isset($input['operation']) ? $input['operation'] : null;
        }

        // Parameters can be null or array
        if (!empty($variables) && !is_array($variables)) {
            try {
                $variables = Json::decode($variables);
            } catch (InvalidArgumentException $e) {
                $variables = null;
            }
        }
        return [$query, $variables, $operation];
    }

    protected function getDebug()
    {
        $debug = false;
        if (YII_ENV_DEV) {
            $debug = Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE;
        }
        return $debug;
    }

    public function formatError(Error $e)
    {
        $previous = $e->getPrevious();
        if ($previous) {
            if ($previous instanceof ValidatorException) {
                return [
                    'validation' => $previous->formatErrors,
                ];
            } else if($previous instanceof HttpException) {
                return [
                    'statusCode' => $previous->statusCode,
                    'message' => $previous->getMessage(),
                ];
            }
        }
        return FormattedError::createFromException($e, $this->getDebug());
    }
}