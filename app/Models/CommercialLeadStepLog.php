<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialLeadStepLog extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'lead_id',
        'step_id',
        'user_id',
        'status',
        'note',
        'scheduled_at',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('lead_step_logs');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CommercialLead::class, 'lead_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(CommercialLeadStep::class, 'step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
