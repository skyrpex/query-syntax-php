<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\AssertsExceptions;
use Pallares\QuerySyntax\Lexer;
use Pallares\QuerySyntax\Parser;

class FeatureTest extends TestCase
{
    protected function token($name, $value, $column)
    {
        return compact('name', 'value', 'column');
    }

    protected function parse($query)
    {
        return (new Parser)->parse(new Lexer($query));
    }

    public function test_1()
    {
        $this->assertEquals([
            'operator' => 'and',
            'children' => [
                ['operator' => 'comparison', 'key' => 'director', 'value' => 'Steven Spielberg'],
                [
                    'operator' => 'or',
                    'children' => [
                        ['operator' => 'comparison', 'key' => 'category', 'value' => 'sci-fi'],
                        ['operator' => 'comparison', 'key' => 'category', 'value' => 'terror'],
                    ],
                ],
            ],
        ], $this->parse('director:"Steven Spielberg" AND (category:"sci-fi" OR category:terror)'));
    }

    public function test_2()
    {
        // FIXME
        $this->assertEquals([
            'operator' => 'and',
            'children' => [
                [
                    'operator' => 'or',
                    'children' => [
                        ['operator' => 'comparison', 'key' => 'category', 'value' => 'x'],
                        ['operator' => 'comparison', 'key' => 'category', 'value' => 'y'],
                    ],
                ],
                [
                    'operator' => 'not',
                    'child' => ['operator' => 'comparison', 'key' => 'category', 'value' => 'x'],
                ],
            ],
        ], $this->parse('(category:x OR category:y) AND NOT category:x'));
    }
}
