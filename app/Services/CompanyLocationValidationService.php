<?php

namespace App\Services;

use App\Models\Company;
use App\Models\TimeEntry;

class CompanyLocationValidationService
{
    public function isCompanyConfigured(Company $company): bool
    {
        return filled($company->company_latitude) && filled($company->company_longitude);
    }

    public function calculateDistanceInMeters(
        float $originLatitude,
        float $originLongitude,
        float $destinationLatitude,
        float $destinationLongitude
    ): float {
        $earthRadius = 6371000.0;

        $latFrom = deg2rad($originLatitude);
        $lonFrom = deg2rad($originLongitude);
        $latTo = deg2rad($destinationLatitude);
        $lonTo = deg2rad($destinationLongitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return $earthRadius * $angle;
    }

    public function analyzeTimeEntry(TimeEntry $timeEntry, ?Company $company = null): array
    {
        $company ??= $timeEntry->company;

        if (! $company) {
            return $this->basePayload(null, false, false);
        }

        $enabled = (bool) $company->location_validation_enabled;
        $isConfigured = $this->isCompanyConfigured($company);
        $allowedRadius = (int) $company->allowed_radius_meters;

        if (! $enabled || ! $isConfigured || blank($timeEntry->latitude) || blank($timeEntry->longitude)) {
            return $this->basePayload($allowedRadius, $enabled, $isConfigured);
        }

        $distanceMeters = $this->calculateDistanceInMeters(
            (float) $company->company_latitude,
            (float) $company->company_longitude,
            (float) $timeEntry->latitude,
            (float) $timeEntry->longitude,
        );

        $isWithinAllowedRadius = $distanceMeters <= $allowedRadius;

        return [
            'enabled' => $enabled,
            'is_configured' => $isConfigured,
            'distance_meters' => round($distanceMeters, 2),
            'allowed_radius_meters' => $allowedRadius,
            'is_within_allowed_radius' => $isWithinAllowedRadius,
            'show_alert' => ! $isWithinAllowedRadius,
            'alert_message' => $isWithinAllowedRadius
                ? null
                : 'Registro realizado fora da área configurada pela empresa.',
        ];
    }

    private function basePayload(?int $allowedRadius, bool $enabled, bool $isConfigured): array
    {
        return [
            'enabled' => $enabled,
            'is_configured' => $isConfigured,
            'distance_meters' => null,
            'allowed_radius_meters' => $allowedRadius,
            'is_within_allowed_radius' => null,
            'show_alert' => false,
            'alert_message' => null,
        ];
    }
}
