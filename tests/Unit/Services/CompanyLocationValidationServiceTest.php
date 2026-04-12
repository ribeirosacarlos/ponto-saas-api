<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\TimeEntry;
use App\Services\CompanyLocationValidationService;
use Tests\TestCase;

class CompanyLocationValidationServiceTest extends TestCase
{
    public function test_it_detects_when_company_location_is_configured(): void
    {
        $company = new Company([
            'company_latitude' => '-23.5505200',
            'company_longitude' => '-46.6333080',
        ]);

        $service = new CompanyLocationValidationService();

        $this->assertTrue($service->isCompanyConfigured($company));
    }

    public function test_it_returns_alert_when_time_entry_is_outside_allowed_radius(): void
    {
        $company = new Company([
            'company_latitude' => '-23.5505200',
            'company_longitude' => '-46.6333080',
            'allowed_radius_meters' => 100,
            'location_validation_enabled' => true,
        ]);

        $timeEntry = new TimeEntry([
            'latitude' => '-23.551900',
            'longitude' => '-46.635500',
        ]);

        $service = new CompanyLocationValidationService();
        $result = $service->analyzeTimeEntry($timeEntry, $company);

        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['is_configured']);
        $this->assertSame(100, $result['allowed_radius_meters']);
        $this->assertFalse($result['is_within_allowed_radius']);
        $this->assertTrue($result['show_alert']);
        $this->assertSame('Registro realizado fora da área configurada pela empresa.', $result['alert_message']);
        $this->assertIsFloat($result['distance_meters']);
    }

    public function test_it_returns_neutral_payload_when_validation_is_disabled(): void
    {
        $company = new Company([
            'company_latitude' => '-23.5505200',
            'company_longitude' => '-46.6333080',
            'allowed_radius_meters' => 100,
            'location_validation_enabled' => false,
        ]);

        $timeEntry = new TimeEntry([
            'latitude' => '-23.550520',
            'longitude' => '-46.633308',
        ]);

        $service = new CompanyLocationValidationService();
        $result = $service->analyzeTimeEntry($timeEntry, $company);

        $this->assertFalse($result['enabled']);
        $this->assertTrue($result['is_configured']);
        $this->assertNull($result['distance_meters']);
        $this->assertNull($result['is_within_allowed_radius']);
        $this->assertFalse($result['show_alert']);
        $this->assertNull($result['alert_message']);
    }
}
