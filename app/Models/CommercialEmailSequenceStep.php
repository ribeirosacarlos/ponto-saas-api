<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialEmailSequenceStep extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sequence_id',
        'template_id',
        'position',
        'name',
        'delay_days',
        'send_time_override',
        'is_active',
        'moves_lead_to_step_id',
    ];

    protected $casts = [
        'position' => 'integer',
        'delay_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('email_sequence_steps');
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(CommercialEmailSequence::class, 'sequence_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommercialEmailTemplate::class, 'template_id');
    }

    public function movesLeadToStep(): BelongsTo
    {
        return $this->belongsTo(CommercialLeadStep::class, 'moves_lead_to_step_id');
    }

    public function nextActive(): ?self
    {
        return static::query()
            ->where('sequence_id', $this->sequence_id)
            ->where('is_active', true)
            ->where('position', '>', $this->position)
            ->orderBy('position')
            ->first();
    }
}
