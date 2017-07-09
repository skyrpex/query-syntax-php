<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\AssertsExceptions;
use Pallares\QuerySyntax\Parser;
use Pallares\QuerySyntax\Exceptions\SemanticException;

class ParserTest extends TestCase
{
    use AssertsExceptions;

    protected function token($name, $value = null, $column = 0)
    {
        return compact('name', 'value', 'column');
    }

    protected function lexer(array $tokens)
    {
        return new Stubs\LexerStub($tokens);
    }

    protected function parse(array $tokens)
    {
        return (new Parser)->parse($this->lexer($tokens));
    }

    public function test_comparison()
    {
        // key:value_1
        $this->assertEquals([
            'operator' => 'and',
            'children' => [
                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value'],
            ],
        ], $this->parse([
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value'),
        ]));
    }

    public function test_operator_or()
    {
        // key:value_1 OR key:value_2
        $this->assertEquals([
            'operator' => 'or',
            'children' => [
                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_1'],
                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_2'],
            ],
        ], $this->parse([
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_1'),
            $this->token('T_LOGICAL_OPERATOR', 'OR'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_2'),
        ]));
    }

    public function test_nested_groups()
    {
        // key:value_1 AND (key:value_2 OR key:value_3)
        $this->assertEquals([
            'operator' => 'and',
            'children' => [
                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_1'],
                [
                    'operator' => 'or',
                    'children' => [
                        ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_2'],
                        ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_3'],
                    ],
                ],
            ],
        ], $this->parse([
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_1'),
            $this->token('T_LOGICAL_OPERATOR', 'AND'),
            $this->token('T_OPEN_GROUP'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_2'),
            $this->token('T_LOGICAL_OPERATOR', 'OR'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_3'),
            $this->token('T_CLOSE_GROUP'),
        ]));
    }

    public function test_multiple_and_operators()
    {
        // key:value_1 AND key:value_2 AND key:value_3
        $this->assertEquals([
            'operator' => 'and',
            'children' => [
                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_1'],
                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_2'],
                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_3'],
            ],
        ], $this->parse([
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_1'),
            $this->token('T_LOGICAL_OPERATOR', 'AND'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_2'),
            $this->token('T_LOGICAL_OPERATOR', 'AND'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_3'),
        ]));
    }

    public function test_negated_comparator()
    {
        // NOT key:value_1
        $this->assertEquals([
            'operator' => 'and',
            'children' => [
                [
                    'operator' => 'not',
                    'child' => ['operator' => 'comparison', 'key' => 'key', 'value' => 'value'],
                ],
            ],
        ], $this->parse([
            $this->token('T_NEGATOR'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value'),
        ]));
    }

    public function test_negated_group()
    {
        // NOT (key:x AND key:y)
        $this->assertEquals([
            'operator' => 'and',
            'children' => [
                [
                    'operator' => 'not',
                    'child' => [
                        'operator' => 'and',
                        'children' => [
                            ['operator' => 'comparison', 'key' => 'key', 'value' => 'x'],
                            ['operator' => 'comparison', 'key' => 'key', 'value' => 'y'],
                        ],
                    ],
                ],
            ],
        ], $this->parse([
            $this->token('T_NEGATOR'),
            $this->token('T_OPEN_GROUP'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'x'),
            $this->token('T_LOGICAL_OPERATOR', 'AND'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'y'),
            $this->token('T_CLOSE_GROUP'),
        ]));
    }

    public function test_complex_negated_groups()
    {
        // NOT (key:x AND key:y) AND NOT (key:z OR key:i)
        $this->assertEquals([
            'operator' => 'and',
            'children' => [
                [
                    'operator' => 'not',
                    'child' => [
                        'operator' => 'and',
                        'children' => [
                            ['operator' => 'comparison', 'key' => 'key', 'value' => 'x'],
                            ['operator' => 'comparison', 'key' => 'key', 'value' => 'y'],
                        ],
                    ],
                ],
                [
                    'operator' => 'not',
                    'child' => [
                        'operator' => 'or',
                        'children' => [
                            ['operator' => 'comparison', 'key' => 'key', 'value' => 'z'],
                            ['operator' => 'comparison', 'key' => 'key', 'value' => 'i'],
                        ],
                    ],
                ],
            ],
        ], $this->parse([
            $this->token('T_NEGATOR'),
            $this->token('T_OPEN_GROUP'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'x'),
            $this->token('T_LOGICAL_OPERATOR', 'AND'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'y'),
            $this->token('T_CLOSE_GROUP'),
            $this->token('T_LOGICAL_OPERATOR', 'AND'),
            $this->token('T_NEGATOR'),
            $this->token('T_OPEN_GROUP'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'z'),
            $this->token('T_LOGICAL_OPERATOR', 'OR'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'i'),
            $this->token('T_CLOSE_GROUP'),
        ]));
    }

    public function test_complex_negated_nested_groups()
    {
        // NOT (key:x AND NOT (key:y OR key:z))
        $this->assertEquals([
            'operator' => 'and',
            'children' => [
                [
                    'operator' => 'not',
                    'child' => [
                        'operator' => 'and',
                        'children' => [
                            ['operator' => 'comparison', 'key' => 'key', 'value' => 'x'],
                            [
                                'operator' => 'not',
                                'child' => [
                                    'operator' => 'or',
                                    'children' => [
                                        ['operator' => 'comparison', 'key' => 'key', 'value' => 'y'],
                                        ['operator' => 'comparison', 'key' => 'key', 'value' => 'z'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $this->parse([
            // NOT (key:x AND NOT (key:y OR key:z))
            $this->token('T_NEGATOR'),
            $this->token('T_OPEN_GROUP'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'x'),
            $this->token('T_LOGICAL_OPERATOR', 'AND'),
            $this->token('T_NEGATOR'),
            $this->token('T_OPEN_GROUP'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'y'),
            $this->token('T_LOGICAL_OPERATOR', 'OR'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'z'),
            $this->token('T_CLOSE_GROUP'),
            $this->token('T_CLOSE_GROUP'),
        ]));
    }

    public function test_default_grouping_order()
    {
        // key:value_1 AND key:value_2 OR key:value_3
        $this->assertEquals([
            'operator' => 'or',
            'children' => [
                [
                    'operator' => 'and',
                    'children' => [
                        ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_1'],
                        ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_2'],
                    ],
                ],
                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_3'],
            ],
        ], $this->parse([
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_1'),
            $this->token('T_LOGICAL_OPERATOR', 'AND'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_2'),
            $this->token('T_LOGICAL_OPERATOR', 'OR'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_3'),
        ]));
    }

    public function test_subgrouping_mixed_operators()
    {
        // key:value_1 OR ((key:value_2 OR key:value_3) AND key:value_4)
        $this->assertEquals([
            'operator' => 'or',
            'children' => [
                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_1'],
                [
                    'operator' => 'and',
                    'children' => [
                        [
                            'operator' => 'or',
                            'children' => [
                                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_2'],
                                ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_3'],
                            ],
                        ],
                        ['operator' => 'comparison', 'key' => 'key', 'value' => 'value_4'],
                    ],
                ],
            ],
        ], $this->parse([
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_1'),
            $this->token('T_LOGICAL_OPERATOR', 'OR'),
            $this->token('T_OPEN_GROUP'),
            $this->token('T_OPEN_GROUP'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_2'),
            $this->token('T_LOGICAL_OPERATOR', 'OR'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_3'),
            $this->token('T_CLOSE_GROUP'),
            $this->token('T_LOGICAL_OPERATOR', 'AND'),
            $this->token('T_TEXT', 'key'),
            $this->token('T_COMPARATOR'),
            $this->token('T_TEXT', 'value_4'),
            $this->token('T_CLOSE_GROUP'),
        ]));
    }

    public function test_too_many_closed_parenthesis_exception()
    {
        $this->assertExceptionIsThrown(function () {
            $this->parse([
                $this->token('T_CLOSE_GROUP'),
            ]);
        }, SemanticException::class);
    }

    public function test_unclosed_parenthesis_exception()
    {
        $this->assertExceptionIsThrown(function () {
            $this->parse([
                $this->token('T_OPEN_GROUP'),
                $this->token('T_OPEN_GROUP'),
                $this->token('T_CLOSE_GROUP'),
            ]);
        }, SemanticException::class);
    }

    public function test_unexpected_logical_operator_after_opening_a_group_exception()
    {
        $this->assertExceptionIsThrown(function () {
            $this->parse([
                $this->token('T_OPEN_GROUP'),
                $this->token('T_LOGICAL_OPERATOR'),
            ]);
        }, SemanticException::class);
    }

    public function test_unexpected_logical_operator_after_negation_operator_exception()
    {
        $this->assertExceptionIsThrown(function () {
            $this->parse([
                $this->token('T_NEGATOR'),
                $this->token('T_LOGICAL_OPERATOR'),
            ]);
        }, SemanticException::class);
    }

    public function test_unexpected_multiple_logical_operators_exception()
    {
        $this->assertExceptionIsThrown(function () {
            $this->parse([
                $this->token('T_LOGICAL_OPERATOR'),
                $this->token('T_LOGICAL_OPERATOR'),
            ]);
        }, SemanticException::class);
    }

    public function test_unexpected_multiple_negation_operators_exception()
    {
        $this->assertExceptionIsThrown(function () {
            $this->parse([
                $this->token('T_NEGATOR'),
                $this->token('T_NEGATOR'),
            ]);
        }, SemanticException::class);
    }
}
