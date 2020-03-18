<?php

namespace mgcode\graphql;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use Traversable;

class Utils
{
    public static function undefined()
    {
        static $undefined;
        return $undefined ?: $undefined = new \stdClass();
    }

    /**
     * @param array|Traversable $traversable
     * @param callable $predicate
     * @return null
     */
    public static function find($traversable, callable $predicate)
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        foreach ($traversable as $key => $value) {
            if ($predicate($value, $key)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * @param $traversable
     * @param callable $predicate
     * @return array
     * @throws \Exception
     */
    public static function filter($traversable, callable $predicate)
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $result = [];
        $assoc = false;
        foreach ($traversable as $key => $value) {
            if (!$assoc && !is_int($key)) {
                $assoc = true;
            }
            if ($predicate($value, $key)) {
                $result[$key] = $value;
            }
        }

        return $assoc ? $result : array_values($result);
    }

    /**
     * @param array|\Traversable $traversable
     * @param callable $fn function($value, $key) => $newValue
     * @return array
     * @throws \Exception
     */
    public static function map($traversable, callable $fn)
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $map = [];
        foreach ($traversable as $key => $value) {
            $map[$key] = $fn($value, $key);
        }
        return $map;
    }

    /**
     * @param $traversable
     * @param callable $fn
     * @return array
     * @throws \Exception
     */
    public static function mapKeyValue($traversable, callable $fn)
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $map = [];
        foreach ($traversable as $key => $value) {
            list($newKey, $newValue) = $fn($value, $key);
            $map[$newKey] = $newValue;
        }
        return $map;
    }

    /**
     * @param $traversable
     * @param callable $keyFn function($value, $key) => $newKey
     * @return array
     * @throws \Exception
     */
    public static function keyMap($traversable, callable $keyFn)
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $map = [];
        foreach ($traversable as $key => $value) {
            $newKey = $keyFn($value, $key);
            if (is_scalar($newKey)) {
                $map[$newKey] = $value;
            }
        }
        return $map;
    }

    /**
     * @param $traversable
     * @param callable $fn
     */
    public static function each($traversable, callable $fn)
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        foreach ($traversable as $key => $item) {
            $fn($item, $key);
        }
    }

    /**
     * Splits original traversable to several arrays with keys equal to $keyFn return
     *
     * E.g. Utils::groupBy([1, 2, 3, 4, 5], function($value) {return $value % 3}) will output:
     * [
     *    1 => [1, 4],
     *    2 => [2, 5],
     *    0 => [3],
     * ]
     *
     * $keyFn is also allowed to return array of keys. Then value will be added to all arrays with given keys
     *
     * @param $traversable
     * @param callable $keyFn function($value, $key) => $newKey(s)
     * @return array
     */
    public static function groupBy($traversable, callable $keyFn)
    {
        self::invariant(is_array($traversable) || $traversable instanceof \Traversable, __METHOD__ . ' expects array or Traversable');

        $grouped = [];
        foreach ($traversable as $key => $value) {
            $newKeys = (array)$keyFn($value, $key);
            foreach ($newKeys as $key) {
                $grouped[$key][] = $value;
            }
        }

        return $grouped;
    }

    /**
     * @param array|Traversable $traversable
     * @param callable $keyFn
     * @param callable $valFn
     * @return array
     */
    public static function keyValMap($traversable, callable $keyFn, callable $valFn)
    {
        $map = [];
        foreach ($traversable as $item) {
            $map[$keyFn($item)] = $valFn($item);
        }
        return $map;
    }

    /**
     * @param $traversable
     * @param callable $predicate
     * @return bool
     */
    public static function every($traversable, callable $predicate)
    {
        foreach ($traversable as $key => $value) {
            if (!$predicate($value, $key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $test
     * @param string $message
     * @param mixed $sprintfParam1
     * @param mixed $sprintfParam2 ...
     * @throws InvariantViolation
     */
    public static function invariant($test, $message = '')
    {
        if (!$test) {
            if (func_num_args() > 2) {
                $args = func_get_args();
                array_shift($args);
                $message = call_user_func_array('sprintf', $args);
            }
            throw new InvariantViolation($message);
        }
    }

    /**
     * @param $var
     * @return string
     */
    public static function getVariableType($var)
    {
        if ($var instanceof Type) {
            // FIXME: Replace with schema printer call
            if ($var instanceof WrappingType) {
                $var = $var->getWrappedType(true);
            }
            return $var->name;
        }
        return is_object($var) ? get_class($var) : gettype($var);
    }

    /**
     * @param mixed $var
     * @return string
     */
    public static function printSafeJson($var)
    {
        if ($var instanceof \stdClass) {
            $var = (array)$var;
        }
        if (is_array($var)) {
            $count = count($var);
            if (!isset($var[0]) && $count > 0) {
                $keys = [];
                $keyCount = 0;
                foreach ($var as $key => $value) {
                    $keys[] = '"' . $key . '"';
                    if ($keyCount++ > 4) {
                        break;
                    }
                }
                $keysLabel = $keyCount === 1 ? 'key' : 'keys';
                $msg = "object with first $keysLabel: " . implode(', ', $keys);
            } else {
                $msg = "array($count)";
            }
            return $msg;
        }
        if ('' === $var) {
            return '(empty string)';
        }
        if (null === $var) {
            return 'null';
        }
        if (false === $var) {
            return 'false';
        }
        if (true === $var) {
            return 'false';
        }
        if (is_string($var)) {
            return "\"$var\"";
        }
        if (is_scalar($var)) {
            return (string)$var;
        }
        return gettype($var);
    }

    /**
     * @param $var
     * @return string
     */
    public static function printSafe($var)
    {
        if ($var instanceof Type) {
            return $var->toString();
        }
        if (is_object($var)) {
            return 'instance of ' . get_class($var);
        }
        if (is_array($var)) {
            $count = count($var);
            if (!isset($var[0]) && $count > 0) {
                $keys = [];
                $keyCount = 0;
                foreach ($var as $key => $value) {
                    $keys[] = '"' . $key . '"';
                    if ($keyCount++ > 4) {
                        break;
                    }
                }
                $keysLabel = $keyCount === 1 ? 'key' : 'keys';
                $msg = "associative array($count) with first $keysLabel: " . implode(', ', $keys);
            } else {
                $msg = "array($count)";
            }
            return $msg;
        }
        if ('' === $var) {
            return '(empty string)';
        }
        if (null === $var) {
            return 'null';
        }
        if (false === $var) {
            return 'false';
        }
        if (true === $var) {
            return 'false';
        }
        if (is_string($var)) {
            return "\"$var\"";
        }
        if (is_scalar($var)) {
            return (string)$var;
        }
        return gettype($var);
    }

    /**
     * UTF-8 compatible chr()
     *
     * @param string $ord
     * @param string $encoding
     * @return string
     */
    public static function chr($ord, $encoding = 'UTF-8')
    {
        if ($ord <= 255) {
            return chr($ord);
        }
        if ($encoding === 'UCS-4BE') {
            return pack("N", $ord);
        } else {
            return mb_convert_encoding(self::chr($ord, 'UCS-4BE'), $encoding, 'UCS-4BE');
        }
    }

    /**
     * UTF-8 compatible ord()
     *
     * @param string $char
     * @param string $encoding
     * @return mixed
     */
    public static function ord($char, $encoding = 'UTF-8')
    {
        if (!$char && '0' !== $char) {
            return 0;
        }
        if (!isset($char[1])) {
            return ord($char);
        }
        if ($encoding !== 'UCS-4BE') {
            $char = mb_convert_encoding($char, 'UCS-4BE', $encoding);
        }
        list(, $ord) = unpack('N', $char);
        return $ord;
    }

    /**
     * Returns UTF-8 char code at given $positing of the $string
     *
     * @param $string
     * @param $position
     * @return mixed
     */
    public static function charCodeAt($string, $position)
    {
        $char = mb_substr($string, $position, 1, 'UTF-8');
        return self::ord($char);
    }

    /**
     * @param $code
     * @return string
     */
    public static function printCharCode($code)
    {
        if (null === $code) {
            return '<EOF>';
        }
        return $code < 0x007F
            // Trust JSON for ASCII.
            ? json_encode(Utils::chr($code))
            // Otherwise print the escaped form.
            : '"\\u' . dechex($code) . '"';
    }
}
