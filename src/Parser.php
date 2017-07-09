<?php

namespace Pallares\QuerySyntax;

use stdClass;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Pallares\QuerySyntax\Exceptions\SemanticException;

class Parser
{
    protected $root;

    protected $unclosedNodes;

    protected $expectedOperators;

    protected $unexpectedOperators;

    public function parse(Contracts\Lexer $lexer)
    {
        $this->root = $this->makeLogicalOperatorNode();
        $this->resetTokenExpectations();
        $this->unclosedNodes = 0;

        $node = $this->root;
        while ($lexer->next()) {
            $node = $this->parseToken($lexer, $node);
        }

        // Check for unmatched parenthesis
        if ($this->unclosedNodes > 0) {
            throw new SemanticException('There are unclosed parenthesis');
        } elseif ($this->unclosedNodes < 0) {
            throw new SemanticException('There are too many closed parenthesis');
        }

        // If there's only one node, we assume an AND operator.
        if (count($this->root->children) === 1) {
            $this->root->operator = 'and';
        }

        return $this->formatNode($this->root);
    }

    public function parseToken(Contracts\Lexer $lexer, stdClass $node)
    {
        $token = $lexer->current();

        $this->validateTokenExpectation($token);
        $this->resetTokenExpectations();

        $parsers = $this->getParsers();
        if (empty($parser = $parsers[$token['name']])) {
            throw new SemanticException("Unrecognized token [{$token['name']}]");
        }

        return $this->{$parser}($lexer, $node);
    }

    protected function validateTokenExpectation(array $token)
    {
        if (count($this->expectedOperators) && ! in_array($token['name'], $this->expectedOperators, true)) {
            $expected = implode(', ', $this->expectedOperators);
            throw new SemanticException("Unexpected token [{$token['name']}]. Expected [$expected].");
        }

        if (count($this->unexpectedOperators) && in_array($token['name'], $this->unexpectedOperators, true)) {
            throw new SemanticException("Unexpected token [{$token['name']}].");
        }
    }

    protected function resetTokenExpectations()
    {
        $this->expectedOperators = [];
        $this->unexpectedOperators = [];
    }

    public function getParsers()
    {
        return [
            'T_WHITESPACE' => 'parseWhitespaceToken',
            'T_TEXT' => 'parseComparisonToken',
            'T_LOGICAL_OPERATOR' => 'parseLogicalOperatorToken',
            'T_NEGATOR' => 'parseNegatorToken',
            'T_OPEN_GROUP' => 'parseOpenGroupToken',
            'T_CLOSE_GROUP' => 'parseCloseGroupToken',
        ];
    }

    public function parseOpenGroupToken(Contracts\Lexer $lexer, stdClass $node)
    {
        $this->validateTokenName($lexer->current(), 'T_OPEN_GROUP');

        $this->unexpectedOperators = ['T_COMPARATOR'];

        $this->unclosedNodes += 1;
        $newNode = $this->makeLogicalOperatorNode(['parent' => $node]);
        $node->children[] = $newNode;
        return $newNode;
    }

    protected function parseCloseGroupToken(Contracts\Lexer $lexer, stdClass $node)
    {
        $this->validateTokenName($lexer->current(), 'T_CLOSE_GROUP');

        $this->unexpectedOperators = ['T_OPEN_GROUP'];

        $this->unclosedNodes -= 1;

        if ($this->unclosedNodes < 0) {
            throw new SemanticException('Unexpected closing parenthesis');
        }

        return $node->parent;
    }

    public function parseWhitespaceToken(Contracts\Lexer $lexer, stdClass $node)
    {
        $this->validateTokenName($key = $lexer->current(), 'T_WHITESPACE');

        return $node;
    }

    public function parseComparisonToken(Contracts\Lexer $lexer, stdClass $node)
    {
        $this->validateTokenName($key = $lexer->current(), 'T_TEXT');
        $this->validateTokenName($comparator = $lexer->next(), 'T_COMPARATOR');
        $this->validateTokenName($value = $lexer->next(), 'T_TEXT');

        $this->unexpectedOperators = ['T_TEXT'];

        $node->children[] = $this->makeComparisonNode(
            $key['value'],
            $value['value']
        );
        return $node;
    }

    public function parseLogicalOperatorToken(Contracts\Lexer $lexer, stdClass $node)
    {
        $this->validateTokenName($operator = $lexer->current(), 'T_LOGICAL_OPERATOR');

        $this->unexpectedOperators = ['T_LOGICAL_OPERATOR', 'T_CLOSE_GROUP'];

        $operator = Str::lower($operator['value']);

        // The group node isn't defined yet.
        if ($node->operator === null) {
            $node->operator = $operator;
            return $node;
        }

        // The logical operator has changed,
        // so we need to build a new group.
        if ($node->operator !== $operator) {
            $parent = $this->parentNode($node);

            $new = $this->makeLogicalOperatorNode([
                'parent' => $parent,
                'operator' => $parent->operator,
                'children' => $node->children,
            ]);

            $parent->operator = $operator;
            $parent->children = [
                $new,
            ];

            return $parent;
        }

        // The logical operator has not changed, so we
        // just return the same node.
        return $node;
    }

    protected function parseNegatorToken(Contracts\Lexer $lexer, stdClass $node)
    {
        $this->validateTokenName($lexer->current(), 'T_NEGATOR');

        $this->expectedOperators = ['T_OPEN_GROUP', 'T_TEXT'];

        $negator = new stdClass;
        $negator->parent = $node;
        $negator->operator = 'not';

        $nextToken = $lexer->next();
        $child = null;
        if ($nextToken['name'] === 'T_OPEN_GROUP') {
            $child = $this->parseToken($lexer, new stdClass);
            $child->parent = $node;
            // dd($child);
        } elseif ($nextToken['name'] === 'T_TEXT') {
            $child = $this->parseToken($lexer, new stdClass)->children[0];
        } else {
            throw new SemanticException("Invalid state.");
        }

        // $negator = new stdClass;
        // $negator->parent = $node;
        // $negator->operator = 'not';
        $negator->child = $child;
        $node->children[] = $negator;

        if ($nextToken['name'] === 'T_OPEN_GROUP') {
            return $child;
        }

        return $node;
    }

    protected function parentNode(stdClass $node)
    {
        return empty($node->parent) ? $this->root : $node->parent;
    }

    protected function validateTokenName($token, $name)
    {
        if (empty($token)) {
            throw new SemanticException('Unexpected end of text');
        }

        if ($token['name'] !== $name) {
            throw new SemanticException("Expected [$name] token, but eceived [{$token['name']}]");
        }
    }

    protected function makeComparisonNode($key, $value)
    {
        $node = new stdClass;
        $node->operator = 'comparison';
        $node->key = $key;
        $node->value = $value;
        return $node;
    }

    protected function makeLogicalOperatorNode(array $options = [])
    {
        $node = new stdClass;
        $node->parent = Arr::get($options, 'parent', null);
        $node->operator = Arr::get($options, 'operator', null);
        $node->children = Arr::get($options, 'children', []);
        return $node;
    }

    protected function formatNode(stdClass $node)
    {
        if (array_key_exists('parent', $node)) {
            unset($node->parent);
        }

        $array = (array) $node;

        if (isset($array['child'])) {
            $array['child'] = $this->formatNode($array['child']);
        }

        if (! empty($array['children'])) {
            $array['children'] = array_map(function (stdClass $node) {
                return $this->formatNode($node);
            }, $array['children']);
        }

        return $array;
    }
}
