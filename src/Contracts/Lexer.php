<?php

namespace Pallares\QuerySyntax\Contracts;

interface Lexer
{
    public function current();

    public function reset();

    public function next();
}
