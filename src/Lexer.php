<?php

namespace Pallares\QuerySyntax;

use Illuminate\Support\Str;
use Pallares\QuerySyntax\Exceptions\RecognitionError;

class Lexer implements Contracts\Lexer
{
    protected $text;

    protected $currentToken;

    protected $offset = 0;

    public function __construct($text)
    {
        $this->text = $text;
    }

    public function current()
    {
        return $this->currentToken;
    }

    public function reset()
    {
        $this->offset = 0;
    }

    public function next()
    {
        if ($this->offset >= Str::length($this->text)) {
            $this->currentToken = false;
            return $this->currentToken;
        }

        if ($match = $this->match($this->text, $this->offset)) {
            $this->offset = $match['offset'];

            $this->currentToken = $match['token'];

            return $this->currentToken;
        }

        throw new RecognitionError($this->text, $this->offset);
    }

    protected function match($text, $offset)
    {
        foreach ($this->getMatchers() as $matcher) {
            if ($match = $this->{$matcher}($text, $offset)) {
                return $match;
            }
        }

        return false;
    }

    protected function getMatchers()
    {
        return [
            'matchWhitespace',
            'matchOpenGroup',
            'matchCloseGroup',
            'matchLogicalOperator',
            'matchComparator',
            'matchNegator',
            'matchWhitespace',
            'matchText',
        ];
    }

    protected function matchText($text, $offset)
    {
        $hits = preg_match('/^("((?:[^"\\\\]|\\\\.)+)"|\w+)/', Str::substr($text, $offset), $matches);
        if (empty($hits)) {
            return false;
        }

        return [
            'token' => [
                'name' => 'T_TEXT',
                'value' => count($matches) === 3 ? stripcslashes($matches[2]) : $matches[1],
                'column' => $offset,
            ],
            'offset' => $offset + Str::length($matches[0]),
        ];
    }

    protected function matchWhitespace($text, $offset)
    {
        return $this->matchWith('/^(\s+)/', 'T_WHITESPACE', $text, $offset);
    }

    protected function matchOpenGroup($text, $offset)
    {
        return $this->matchWith('/^(\()/', 'T_OPEN_GROUP', $text, $offset);
    }

    protected function matchCloseGroup($text, $offset)
    {
        return $this->matchWith('/^(\))/', 'T_CLOSE_GROUP', $text, $offset);
    }

    protected function matchLogicalOperator($text, $offset)
    {
        return $this->matchWith('/^(AND|OR)/', 'T_LOGICAL_OPERATOR', $text, $offset);
    }

    protected function matchNegator($text, $offset)
    {
        return $this->matchWith('/^(NOT)/', 'T_NEGATOR', $text, $offset);
    }

    protected function matchComparator($text, $offset)
    {
        return $this->matchWith('/^(:)/', 'T_COMPARATOR', $text, $offset);
    }

    protected function matchWith($regexp, $name, $text, $offset)
    {
        $hits = preg_match($regexp, Str::substr($text, $offset), $matches);
        if (empty($hits)) {
            return false;
        }

        return [
            'token' => [
                'name' => $name,
                'value' => $matches[1],
                'column' => $offset,
            ],
            'offset' => $offset + Str::length($matches[0]),
        ];
    }
}
