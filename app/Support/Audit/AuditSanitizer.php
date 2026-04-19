<?php

namespace App\Support\Audit;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JsonSerializable;

class AuditSanitizer
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEY_PARTS = [
        'password',
        'token',
        'secret',
        'credential',
        'api_key',
        'apikey',
        'invite_code',
        'remember_token',
    ];

    public static function sanitize(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return self::sanitize($value->attributesToArray());
        }

        if ($value instanceof Collection) {
            return self::sanitize($value->all());
        }

        if ($value instanceof Arrayable) {
            return self::sanitize($value->toArray());
        }

        if ($value instanceof JsonSerializable) {
            return self::sanitize($value->jsonSerialize());
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$key] = self::isSensitiveKey((string) $key)
                    ? self::REDACTED
                    : self::sanitize($item);
            }

            return $sanitized;
        }

        return $value;
    }

    public static function diff(array $before, array $after): array
    {
        $keys = collect(array_keys($before))
            ->merge(array_keys($after))
            ->unique()
            ->values();

        $oldValues = [];
        $newValues = [];

        foreach ($keys as $key) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            if (self::normalizeForComparison($oldValue) === self::normalizeForComparison($newValue)) {
                continue;
            }

            $oldValues[$key] = $oldValue;
            $newValues[$key] = $newValue;
        }

        return [$oldValues, $newValues];
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = str_replace(['-', '.'], '_', strtolower($key));

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeForComparison(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
