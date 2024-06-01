<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        preg_match_all('/\W(\?[dfa#]?|{[^{}]+})/i', $query, $matches);
        if (empty($matches[1])) {
            return $query;
        }
        if (count($matches[1]) != count($args)) {
            throw new Exception('Wrong number of arguments');
        }
        for ($i = 0; $i < count($matches[1]); $i++) {
            if ($matches[1][$i] == '?') {
                $query = preg_replace('/\?/', $this->castArgument($args[$i]), $query, 1);
            } else if ($matches[1][$i] === '?#') {
                if (!is_array($args[$i])) {
                    $query = preg_replace('/\?#/', $args[$i], $query, 1);
                } else {
                    $arr = array_map(function ($arg) {
                        return '`' . $arg . '`';
                    }, $args[$i]);
                    $query = preg_replace('/\?#/', implode(', ', $arr), $query, 1);
                }
            } else if ($matches[1][$i] === '?d') {
                $query = preg_replace('/\?d/', $args[$i], $query, 1);
            } else if ($matches[1][$i] === '?a') {
                $query = preg_replace('/\?a/', $this->castArray($args[$i]), $query, 1);
            } else {
                echo $matches[1][$i] . "\n";
                echo "args[i]=" . $args[$i] . "\n";
//                $str = sprintf("preg_replace('/%s/', '%s')", $matches[1][$i], $this->buildQuery($matches[1][$i], $args[$i]));
//                $query = preg_replace()
            }
        }
        echo $query . "\n";
        return $query;
    }

    public function skip()
    {
//        throw new Exception();
    }

    public function castArgument($arg)
    {
        if (is_numeric($arg)) {
            return $arg;
        } else if (is_null($arg)) {
            return 'null';
        } else if (strtolower($arg) === 'null') {
            return 'null';
        } else if (strtolower($arg) === 'false') {
            return '0';
        } else if (strtolower($arg) === 'true') {
            return '1';
        } else if (is_array($arg)) {
            throw new Exception('Without specification only int, float, bool and null are allowed, array provided');
        }
        return "'" . $this->mysqli->real_escape_string($arg) . "'";
    }

    private function castArray($array): string
    {
        if (!is_array($array)) {
            throw new Exception('Expected array, got "' . gettype($array) . '"');
        }
        $result = "";
        foreach ($array as $key => $value) {
            if (is_numeric($key)) {
                if (is_numeric($value)) {
                    return implode(", ", $array);
                } else {
                    return implode(", " , array_map(function ($value) {
                        return '\'' . $this->mysqli->real_escape_string($value) . '\'';
                    }, $array));
                }
            } else {
                $result .= '`' . $key . '`' . ' = ' . $this->castArgument($value) . ', ';
            }
        }
        return strlen($result) ? substr($result, 0, -2) : $result;
    }

    private function buildOneArgument(string $query, $arg): string
    {

    }
}