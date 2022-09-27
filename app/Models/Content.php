<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Content extends Model
{
    use SoftDeletes;

    protected $table = "content";

    protected $fillable = ['project_id', 'collection_id', 'locale', 'created_by', 'updated_by', 'published_at', 'published_by'];

    protected $hidden = ['deleted_at'];

    public function meta(){
        return $this->hasMany('App\Models\ContentMeta');
    }

    public function collection(){
        return $this->belongsTo('App\Models\Collection');
    }
}
