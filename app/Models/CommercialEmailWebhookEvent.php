<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialEmailWebhookEvent extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'provider_event_id',
        'type',
        'payload_json',
        'processed_at',
        'processing_error',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'processed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('email_webhook_events');
    }
}
