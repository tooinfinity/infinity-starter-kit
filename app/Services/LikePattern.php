<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
        return $query->whereRaw("{$column} LIKE ? ESCAPE ?", [$pattern, self::ESCAPE_CHARACTER]);
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
        return $query->orWhereRaw("{$column} LIKE ? ESCAPE ?", [$pattern, self::ESCAPE_CHARACTER]);
    }

    /**
     * Get database-specific JSON path extraction SQL.
     *
     * @param  literal-string  $column
     * @param  literal-string  $path
     * @return literal-string
     */
    public static function jsonPath(string $column, string $path, ?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();

        if ($path === 'actor.email') {
            return match ($driver) {
                'pgsql' => "(\"{$column}\"->'actor'->>'email')",
                'mysql', 'mariadb' => "json_unquote(json_extract(`{$column}`, '$.\"actor\".\"email\"'))",
                default => "json_extract(\"{$column}\", '$.\"actor\".\"email\"')",
            };
        }

        return match ($driver) {
            'pgsql' => "(\"{$column}\"->'actor'->>'name')",
            'mysql', 'mariadb' => "json_unquote(json_extract(`{$column}`, '$.\"actor\".\"name\"'))",
            default => "json_extract(\"{$column}\", '$.\"actor\".\"name\"')",
        };
    }

    /**
     * Add an OR WHERE json_path LIKE ? ESCAPE ? clause to the query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     * @param  literal-string  $path
     * @return Builder<TModel>
     */
    public static function orWhereJson(Builder $query, string $column, string $path, string $pattern, ?string $driver = null): Builder
    {
        $columnSql = self::jsonPath($column, $path, $driver);

        return $query->orWhereRaw("{$columnSql} LIKE ? ESCAPE ?", [$pattern, self::ESCAPE_CHARACTER]);
    }
}
