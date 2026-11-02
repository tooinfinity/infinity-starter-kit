<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use InvalidArgumentException;

/**
 * Named feature presets for non-interactive installer setup.
 */
final readonly class Preset
{
    public const string MINIMAL = Profile::MINIMAL;

    public const string STANDARD = Profile::STANDARD;

    public const string FULL = Profile::FULL;

    public function __construct(
        public string $name,
    ) {}

    /**
     * @return array{auth_features: list<string>, optional_modules: list<string>}
     */
    public static function answersFor(string $name): array
    {
        $normalized = self::normalize($name);

        if (! in_array($normalized, self::names(), true) && $normalized !== Profile::DEFAULT) {
            throw new InvalidArgumentException(sprintf(
                'Unknown preset [%s]. Available presets: %s.',
                $name,
                implode(', ', self::names()),
            ));
        }

        return Profile::answersFor($normalized);
    }

    /**
     * Resolve a preset by name or throw an InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     */
    public static function fromName(string $name): self
    {
        $normalized = self::normalize($name);

        if ($normalized === Profile::DEFAULT) {
            $normalized = self::STANDARD;
        }

        if (! in_array($normalized, self::names(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown preset [%s]. Available presets: %s.',
                $name,
                implode(', ', self::names()),
            ));
        }

        return new self($normalized);
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return array_map(fn (string $name): self => new self($name), self::names());
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            self::MINIMAL,
            self::STANDARD,
            self::FULL,
        ];
    }

    /**
     * @return array{auth_features: list<string>, optional_modules: list<string>}
     */
    public function answers(): array
    {
        return self::answersFor($this->name);
    }

    private static function normalize(string $name): string
    {
        return mb_strtolower(mb_trim($name));
    }
}
