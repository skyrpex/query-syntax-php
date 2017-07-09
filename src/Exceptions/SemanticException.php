<?php

namespace Pallares\QuerySyntax\Exceptions;

class SemanticException extends Exception
{
    public function __construct($text)
    {
        parent::__construct("Semantic error: $text");
    }
}
