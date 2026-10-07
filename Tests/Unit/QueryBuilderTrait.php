<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * A query builder that writes down what it is asked for instead of talking to a database,
 * so a constraint can be asserted as a string.
 */
trait QueryBuilderTrait
{
    private function queryBuilder(): QueryBuilder&MockObject
    {
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturnCallback(static fn(string $field, $value): string => $field . ' = ' . $value);
        $expressionBuilder->method('neq')->willReturnCallback(static fn(string $field, $value): string => $field . ' <> ' . $value);
        $expressionBuilder->method('gt')->willReturnCallback(static fn(string $field, $value): string => $field . ' > ' . $value);
        $expressionBuilder->method('gte')->willReturnCallback(static fn(string $field, $value): string => $field . ' >= ' . $value);
        $expressionBuilder->method('lte')->willReturnCallback(static fn(string $field, $value): string => $field . ' <= ' . $value);
        $expressionBuilder->method('in')->willReturnCallback(static fn(string $field, $value): string => $field . ' IN (' . $value . ')');
        $expressionBuilder->method('inSet')->willReturnCallback(static fn(string $field, string $value): string => 'FIND_IN_SET(' . $value . ', ' . $field . ')');
        $expressionBuilder->method('and')->willReturnCallback(static fn(...$parts): CompositeExpression => CompositeExpression::and(...$parts));
        $expressionBuilder->method('or')->willReturnCallback(static fn(...$parts): CompositeExpression => CompositeExpression::or(...$parts));

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('quote')->willReturnCallback(static fn(string $input): string => "'" . $input . "'");
        $queryBuilder->method('createNamedParameter')->willReturnCallback(
            static fn($value, $type = null): string => is_array($value) ? implode(',', $value) : (string)$value
        );

        return $queryBuilder;
    }
}
