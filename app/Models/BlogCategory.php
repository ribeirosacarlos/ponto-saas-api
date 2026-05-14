<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    use HasUuids;

    protected $table = 'blog_categories';

    protected $fillable = [
        'key',
        'label_pt',
        'label_es',
        'label_en',
        'sort_order',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category', 'key');
    }
}
