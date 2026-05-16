<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class BlogFaqItem extends Model
{
    use HasUuids, HasTranslations;

    public $timestamps = false;
    protected $table = 'blog_faq_items';

    public array $translatable = ['question', 'answer'];

    protected $fillable = ['post_id', 'question', 'answer', 'order_index'];
}
