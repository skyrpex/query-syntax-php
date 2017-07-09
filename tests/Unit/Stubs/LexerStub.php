<?php

namespace Tests\Unit\Stubs;

use Pallares\QuerySyntax\Contracts\Lexer;

class LexerStub implements Lexer
{
    protected $tokens;

    protected $index;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
        $this->reset();
    }

    public function current()
    {
        if (isset($this->tokens[$this->index])) {
            return $this->tokens[$this->index];
        }

        return false;
    }

    public function reset()
    {
        $this->index = -1;
    }

    public function next()
    {
        $this->index += 1;
        return $this->current();
    }
}
