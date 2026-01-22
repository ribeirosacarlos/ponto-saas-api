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
}
