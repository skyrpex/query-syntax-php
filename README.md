# Query Syntax

This package allows you to parse Algolia-like queries into sort of a AST.

## Example

```php
$query = 'director:"Steven Spielberg" AND (category:"sci-fi" OR category:terror)';

$lexer = new Pallares\QuerySyntax\Lexer(query);

$ast = (new Pallares\QuerySyntax\Parser)->parse(lexer);
```

The AST looks like this:

```php
$ast === [
    'operator' => 'and',
    'children' => [
        ['operator' => 'comparison', 'key' => 'director', 'value' => 'Steven Spielberg'],
        [
            'operator' => 'or',
            'children' => [
                ['operator' => 'comparison', 'key' => 'category', 'value' => 'sci-fi'],
                ['operator' => 'comparison', 'key' => 'category', 'value' => 'terror'],
            ],
        ]
    ],
];
```
