<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialAffiliateBonus extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'affiliate_id',
        'year',
        'month',
        'clients_count',
        'bonus_every_clients',
        'bonus_amount',
        'total_bonus_amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'clients_count' => 'integer',
        'bonus_every_clients' => 'integer',
        'bonus_amount' => 'decimal:2',
        'total_bonus_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('affiliate_bonuses');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(CommercialAffiliate::class, 'affiliate_id');
    }
}
