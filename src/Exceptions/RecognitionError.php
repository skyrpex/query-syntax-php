<?php

namespace Pallares\QuerySyntax\Exceptions;

class RecognitionError extends Exception
{
    protected $text;

    protected $offset;

    public function __construct($text, $offset)
    {
        parent::__construct("Unrecognized character at column [{$offset}]");
        $this->text = $text;
        $this->offset = $offset;
    }

    public function getText()
    {
        return $this->text;
    }

    public function getOffset()
    {
        return $this->offset;
    }
}
