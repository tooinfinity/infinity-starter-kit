<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

final readonly class LikePattern
{
    public const string ESCAPE_CHARACTER = '\\';

    /**
     * Escape LIKE wildcard characters (%, _, \) in the given string.
     */
    public static function escape(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Build a contains LIKE pattern: %escaped%
     */
    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }

    /**
     * Build a prefix (starts-with) LIKE pattern: escaped%
     */
    public static function startsWith(string $value): string
    {
        return self::escape($value).'%';
    }

    /**
     * Build a suffix (ends-with) LIKE pattern: %escaped
     */
    public static function endsWith(string $value): string
    {
        return '%'.self::escape($value);
    }

    /**
     * Add a WHERE ... LIKE ? ESCAPE ? clause to the query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     * @return Builder<TModel>
     */
    public static function where(Builder $query, string $column, string $pattern): Builder
    {
        /** @var literal-string $wrapped */
        $wrapped = str_contains($column, '->')
            ? $query->getQuery()->getGrammar()->wrap($column)
            : $column;

        return $query->whereRaw("{$wrapped} LIKE ? ESCAPE ?", [$pattern, self::ESCAPE_CHARACTER]);
    }

    /**
     * Add an OR WHERE ... LIKE ? ESCAPE ? clause to the query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     * @return Builder<TModel>
     */
    public static function orWhere(Builder $query, string $column, string $pattern): Builder
    {
        /** @var literal-string $wrapped */
        $wrapped = str_contains($column, '->')
            ? $query->getQuery()->getGrammar()->wrap($column)
            : $column;

        return $query->orWhereRaw("{$wrapped} LIKE ? ESCAPE ?", [$pattern, self::ESCAPE_CHARACTER]);
    }
}
