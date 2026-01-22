<?php

namespace App\Support;

use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CompanyTime
{
    public const DEFAULT_TIMEZONE = 'Europe/Madrid';

    /**
     * Resolve the timezone string from the current request context.
     */
    public static function companyTz(Request $request): string
    {
        $company = $request->user()?->company;

        return self::resolveTimezone($company);
    }

    public static function resolveTimezone(?Company $company): string
    {
        $timezone = $company?->timezone ?? config('app.timezone') ?? self::DEFAULT_TIMEZONE;

        return self::normalizeTimezone($timezone);
    }

    public static function availableTimezones(): array
    {
        return timezone_identifiers_list();
    }

    public static function parseToUtc(string $value, string $timezone): CarbonImmutable
    {
        $local = CarbonImmutable::parse($value, $timezone);

        if ($local->getTimezone()->getName() !== 'UTC') {
            $local = $local->setTimezone('UTC');
        }

        return $local;
    }

    /**
     * Interprets a date string as the full local day and returns the UTC bounds.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public static function dayRangeToUtc(string $date, string $timezone): array
    {
        $localStart = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $localEnd = $localStart->endOfDay();

        return [
            $localStart->setTimezone('UTC'),
            $localEnd->setTimezone('UTC'),
        ];
    }

    private static function normalizeTimezone(string $timezone): string
    {
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return self::DEFAULT_TIMEZONE;
        }

        return $timezone;
    }
}
