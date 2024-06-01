<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private const SPECIAL_VALUE = 'SPECIAL VALUE';
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
                $query = $this->replaceFirst($query, '?', $this->castArgument($args[$i]));
            } else if ($matches[1][$i] === '?#') {
                if (!is_array($args[$i])) {
                    $query = $this->replaceFirst($query, '?#', '`' . $args[$i] . '`');
                } else {
                    $arr = array_map(function ($arg) {
                        return '`' . $arg . '`';
                    }, $args[$i]);
                    $query = $this->replaceFirst($query, '?#', implode(', ', $arr));
                }
            } else if ($matches[1][$i] === '?d') {
                $query = $this->replaceFirst($query, '?d', $args[$i]);
            } else if ($matches[1][$i] === '?a') {
                $query = $this->replaceFirst($query, '?a', $this->castArray($args[$i]));
            } else {
                if ($args[$i] === self::SPECIAL_VALUE) {
                    $query = $this->replaceFirst($query, $matches[1][$i]);
                } else {
                    $block = $this->buildQuery($matches[1][$i], [$args[$i]]);
                    $query = $this->replaceFirst($query, $matches[1][$i], trim($block, '{}'));
                }
            }
        }
        return $query;
    }

    public function skip()
    {
        return self::SPECIAL_VALUE;
    }

    public function castArgument($arg)
    {
        if (is_numeric($arg)) {
            return $arg;
        } else if (is_null($arg)) {
            return 'NULL';
        } else if (strtolower($arg) === 'null') {
            return 'NULL';
        } else if (strtolower($arg) === 'false') {
            return '0';
        } else if (strtolower($arg) === 'true') {
            return '1';
        } else if (is_array($arg)) {
            throw new Exception('Without specification only int, float, bool and null are allowed but array provided');
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
        return trim($result, ', ');
    }

    private function replaceFirst(string $haystack, string $needle, $replace = ''): string
    {
        $pos = strpos($haystack, $needle);
        if ($pos !== false) {
            return substr_replace($haystack, $replace, $pos, strlen($needle));
        }
        return $haystack;
    }
}