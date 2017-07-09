<?php

namespace Pallares\QuerySyntax;

use Illuminate\Support\Str;
use Pallares\QuerySyntax\Exceptions\RecognitionError;

class SkipWhitespaceLexer implements Contracts\Lexer
{
    protected $lexer;

    public function __construct(Contracts\Lexer $lexer)
    {
        $this->lexer = $lexer;
    }

    public function current()
    {
        $token = $this->lexer->current();

        if ($token && $token['name'] === 'T_WHITESPACE') {
            return $this->next();
        }

        return $token;
    }

    public function reset()
    {
        $this->lexer->reset();
    }

    public function next()
    {
        $token = $this->lexer->next();

        while ($token && $token['name'] === 'T_WHITESPACE') {
            $token = $this->lexer->next();
        }

        return $token;
    }
}
