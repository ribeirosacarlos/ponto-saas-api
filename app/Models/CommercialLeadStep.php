<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialLeadStep extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'position',
        'default_due_days',
        'active',
        'is_final',
    ];

    protected $casts = [
        'position' => 'integer',
        'default_due_days' => 'integer',
        'active' => 'boolean',
        'is_final' => 'boolean',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('lead_steps');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CommercialLead::class, 'current_step_id');
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(CommercialLeadStepLog::class, 'step_id');
    }

    public function nextActive(): ?self
    {
        return static::query()
            ->where('active', true)
            ->where('position', '>', $this->position)
            ->orderBy('position')
            ->first();
    }
}
