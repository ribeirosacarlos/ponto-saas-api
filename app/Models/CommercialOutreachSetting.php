<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialOutreachSetting extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'singleton_key',
        'is_globally_paused',
        'paused_at',
        'paused_by_user_id',
        'pause_reason',
        'daily_send_limit',
        'monthly_send_limit',
        'sending_window_start_time',
        'sending_window_end_time',
        'sending_days',
        'timezone',
        'min_gap_seconds_between_sends',
        'max_sends_per_dispatch_run',
        'bounce_soft_threshold',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_globally_paused' => 'boolean',
        'paused_at' => 'datetime',
        'daily_send_limit' => 'integer',
        'monthly_send_limit' => 'integer',
        'sending_days' => 'array',
        'min_gap_seconds_between_sends' => 'integer',
        'max_sends_per_dispatch_run' => 'integer',
        'bounce_soft_threshold' => 'integer',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('outreach_settings');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['singleton_key' => 'default'],
            ['sending_days' => ['mon', 'tue', 'wed', 'thu', 'fri']]
        );
    }
}
