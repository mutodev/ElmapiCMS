<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContentMeta extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'content_meta';

    protected $fillable = ['project_id', 'collection_id', 'content_id', 'field_name', 'value'];
}
