<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\AssertsExceptions;
use Pallares\QuerySyntax\Lexer;
use Pallares\QuerySyntax\Exceptions\RecognitionError;

class LexerTest extends TestCase
{
    use AssertsExceptions;

    protected function token($name, $value, $column)
    {
        return compact('name', 'value', 'column');
    }

    protected function tokenize($text)
    {
        $lexer = new Lexer($text);
        $tokens = [];
        while ($token = $lexer->next()) {
            $tokens[] = $token;
        }
        return $tokens;
    }

    public function test()
    {
        $this->assertEquals([
            $this->token('T_TEXT', 'key', 0),
            $this->token('T_COMPARATOR', ':', 3),
            $this->token('T_TEXT', 'value', 4),
        ], $this->tokenize('key:value'));

        $this->assertEquals([
            $this->token('T_OPEN_GROUP', '(', 0),
            $this->token('T_TEXT', 'text', 1),
            $this->token('T_CLOSE_GROUP', ')', 5),
        ], $this->tokenize('(text)'));

        $this->assertEquals([
            $this->token('T_TEXT', 'x', 0),
            $this->token('T_WHITESPACE', ' ', 1),
            $this->token('T_LOGICAL_OPERATOR', 'AND', 2),
            $this->token('T_WHITESPACE', ' ', 5),
            $this->token('T_TEXT', 'y', 6),
        ], $this->tokenize('x AND y'));

        $this->assertEquals([
            $this->token('T_NEGATOR', 'NOT', 0),
            $this->token('T_WHITESPACE', ' ', 3),
            $this->token('T_TEXT', 'x', 4),
        ], $this->tokenize('NOT x'));

        $this->assertEquals([
            $this->token('T_TEXT', 'quoted text', 0),
        ], $this->tokenize('"quoted text"'));

        $this->assertEquals([
            $this->token('T_TEXT', 'quoted "text"', 0),
        ], $this->tokenize('"quoted \\"text\\""'));

        $this->assertEquals([
            $this->token('T_TEXT', 'hello', 0),
            $this->token('T_WHITESPACE', ' ', 7),
            $this->token('T_TEXT', 'world', 8),
        ], $this->tokenize('"hello" world'));

        $lexer = new Lexer('"bad"text"');
        $this->assertEquals($this->token('T_TEXT', 'bad', 0), $lexer->next());
        $this->assertEquals($this->token('T_TEXT', 'text', 5), $lexer->next());
        $this->assertExceptionIsThrown(function () use ($lexer) {
            $lexer->next();
        }, RecognitionError::class);
    }
}
