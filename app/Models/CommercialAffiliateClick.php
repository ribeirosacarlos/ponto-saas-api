<?php

namespace App\Models;

use App\Support\Commercial\CommercialSchema;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialAffiliateClick extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'affiliate_id',
        'ip_hash',
        'user_agent',
        'referer',
        'landing_page',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return CommercialSchema::table('affiliate_clicks');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(CommercialAffiliate::class, 'affiliate_id');
    }
}
