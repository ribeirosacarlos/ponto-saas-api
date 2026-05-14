<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BlogFaqItem extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'blog_faq_items';

    protected $fillable = ['post_id', 'question', 'answer', 'order_index'];
}
