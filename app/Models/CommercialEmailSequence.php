<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommercialEmailSequence extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'status',
        'timezone',
        'created_by_user_id',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('email_sequences');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(CommercialEmailSequenceStep::class, 'sequence_id')->orderBy('position');
    }

    public function activeSteps(): HasMany
    {
        return $this->steps()->where('is_active', true);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CommercialEmailSequenceEnrollment::class, 'sequence_id');
    }

    public function firstActiveStep(): ?CommercialEmailSequenceStep
    {
        return $this->activeSteps()->orderBy('position')->first();
    }
}
