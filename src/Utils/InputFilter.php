<?php

namespace Luxullus\LexBridge\Utils;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class InputFilter
{
    /**
     * Check if a filter value is provided (not null or empty string).
     *
     * @param array $filters
     * @param string $key
     * @return bool
     */
    public static function filterValueProvided(array $filters, string $key): bool
    {
        if (!array_key_exists($key, $filters)) {
            return false;
        }
        $value = $filters[$key];
        return $value !== null && $value !== '';
    }
    /**
     * Normalize and validate a date filter value from an array.
     *
     * @param array $filters
     * @param string $key
     * @param bool $isLowerBoundary If true, set time to 00:00:00; if false, set to 23:59:59
     * @param bool $optional If false and value is missing, throw exception
     * @return DateTimeImmutable|null
     */
    public static function filterDateValueProvided(
        array $filters,
        string $key,
        bool $isLowerBoundary = true,
        bool $optional = false
    ): ?DateTimeImmutable {
        if (!array_key_exists($key, $filters) || $filters[$key] === null || $filters[$key] === '') {
            if ($optional) {
                return null;
            }
            throw new InvalidArgumentException(sprintf('Filter "%s" is required.', $key));
        }

        $value = $filters[$key];

        if ($value instanceof DateTimeImmutable) {
            $date = $value;
        } elseif ($value instanceof DateTimeInterface) {
            $date = DateTimeImmutable::createFromInterface($value);
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                throw new InvalidArgumentException(sprintf('Filter "%s" expects a non-empty string.', $key));
            }
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $trimmed) ?: new DateTimeImmutable($trimmed);
        } else {
            throw new InvalidArgumentException(sprintf('Unsupported value for filter "%s".', $key));
        }

        return $isLowerBoundary
            ? $date->setTime(0, 0, 0)
            : $date->setTime(23, 59, 59);
    }
}
