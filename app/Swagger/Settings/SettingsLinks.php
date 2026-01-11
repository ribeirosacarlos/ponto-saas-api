<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsLinks",
 *     @OA\Property(property="checkout_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="customer_portal_url", type="string", format="uri", nullable=true)
 * )
 */
class SettingsLinks {}
