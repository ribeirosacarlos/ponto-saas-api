<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BlogTocItem extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'blog_toc_items';

    protected $fillable = ['post_id', 'label', 'href', 'order_index'];
}
