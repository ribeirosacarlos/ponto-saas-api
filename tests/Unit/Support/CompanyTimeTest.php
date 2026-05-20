<?php

namespace Tests\Unit\Support;

use App\Support\CompanyTime;
use Tests\TestCase;

class CompanyTimeTest extends TestCase
{
    public function test_day_range_to_utc_respects_company_timezone_and_crosses_midnight()
    {
        [$fromUtc, $toUtc] = CompanyTime::dayRangeToUtc('2026-01-02', 'America/Sao_Paulo');

        $this->assertEquals('2026-01-02 03:00:00', $fromUtc->toDateTimeString());
        $this->assertEquals('2026-01-03 02:59:59', $toUtc->toDateTimeString());
    }

    public function test_normalize_date_input_keeps_the_literal_day_from_iso_datetime_values(): void
    {
        $this->assertSame(
            '2026-01-02',
            CompanyTime::normalizeDateInput('2026-01-02T23:59:59-03:00', 'UTC')
        );
    }
}
