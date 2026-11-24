<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\LikePattern;

test('escape adds slashes to like wildcards and backslashes', function (): void {
    expect(LikePattern::escape('normal-text'))->toBe('normal-text')
        ->and(LikePattern::escape(''))->toBe('')
        ->and(LikePattern::escape('%'))->toBe('\%')
        ->and(LikePattern::escape('_'))->toBe('\_')
        ->and(LikePattern::escape('\\'))->toBe('\\\\')
        ->and(LikePattern::escape('100%_complete\\done'))->toBe('100\%\_complete\\\\done');
});

test('contains wraps escaped string with percent wildcards', function (): void {
    expect(LikePattern::contains('search'))->toBe('%search%')
        ->and(LikePattern::contains('%'))->toBe('%\%%')
        ->and(LikePattern::contains('_'))->toBe('%\_%')
        ->and(LikePattern::contains('\\'))->toBe('%\\\\%');
});

test('startsWith appends percent wildcard to escaped string', function (): void {
    expect(LikePattern::startsWith('prefix'))->toBe('prefix%')
        ->and(LikePattern::startsWith('%'))->toBe('\%%')
        ->and(LikePattern::startsWith('_'))->toBe('\_%')
        ->and(LikePattern::startsWith('\\'))->toBe('\\\\%');
});

test('endsWith prepends percent wildcard to escaped string', function (): void {
    expect(LikePattern::endsWith('suffix'))->toBe('%suffix')
        ->and(LikePattern::endsWith('%'))->toBe('%\%')
        ->and(LikePattern::endsWith('_'))->toBe('%\_')
        ->and(LikePattern::endsWith('\\'))->toBe('%\\\\');
});

test('where applies raw like clause with escape character on eloquent builder', function (): void {
    $eloquentQuery = User::query();
    LikePattern::where($eloquentQuery, 'name', LikePattern::contains('test'));

    expect($eloquentQuery->toSql())->toContain('name LIKE ? ESCAPE ?')
        ->and($eloquentQuery->getBindings())->toContain('%test%')
        ->and($eloquentQuery->getBindings())->toContain(LikePattern::ESCAPE_CHARACTER);
});

test('orWhere applies raw or-like clause with escape character on eloquent builder', function (): void {
    $eloquentQuery = User::query();
    LikePattern::where($eloquentQuery, 'name', LikePattern::contains('test'));
    LikePattern::orWhere($eloquentQuery, 'email', LikePattern::contains('test'));

    expect($eloquentQuery->toSql())->toContain('name LIKE ? ESCAPE ? or email LIKE ? ESCAPE ?')
        ->and($eloquentQuery->getBindings())->toEqual([
            '%test%',
            LikePattern::ESCAPE_CHARACTER,
            '%test%',
            LikePattern::ESCAPE_CHARACTER,
        ]);
});

test('jsonPath formats extraction sql correctly across drivers', function (): void {
    // pgsql
    expect(LikePattern::jsonPath('properties', 'actor.name', 'pgsql'))->toBe('("properties"->\'actor\'->>\'name\')')
        ->and(LikePattern::jsonPath('properties', 'actor.email', 'pgsql'))->toBe('("properties"->\'actor\'->>\'email\')');

    // mysql and mariadb
    expect(LikePattern::jsonPath('properties', 'actor.name', 'mysql'))->toBe('json_unquote(json_extract(`properties`, \'$."actor"."name"\'))')
        ->and(LikePattern::jsonPath('properties', 'actor.email', 'mysql'))->toBe('json_unquote(json_extract(`properties`, \'$."actor"."email"\'))')
        ->and(LikePattern::jsonPath('properties', 'actor.name', 'mariadb'))->toBe('json_unquote(json_extract(`properties`, \'$."actor"."name"\'))');

    // sqlite / default
    expect(LikePattern::jsonPath('properties', 'actor.name', 'sqlite'))->toBe('json_extract("properties", \'$."actor"."name"\')')
        ->and(LikePattern::jsonPath('properties', 'actor.email', 'sqlite'))->toBe('json_extract("properties", \'$."actor"."email"\')')
        ->and(LikePattern::jsonPath('properties', 'actor.name'))->toBe('json_extract("properties", \'$."actor"."name"\')');
});

test('orWhereJson applies raw or-like clause with escape character and json column on eloquent builder', function (): void {
    $eloquentQuery = User::query();
    LikePattern::orWhereJson($eloquentQuery, 'properties', 'actor.name', LikePattern::contains('test'));

    expect($eloquentQuery->toSql())->toContain('json_extract("properties", \'$."actor"."name"\') LIKE ? ESCAPE ?')
        ->and($eloquentQuery->getBindings())->toEqual([
            '%test%',
            LikePattern::ESCAPE_CHARACTER,
        ]);
});
