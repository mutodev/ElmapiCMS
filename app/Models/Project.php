<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasApiTokens, HasFactory;

    protected $table = "projects";

    protected $fillable = ['name', 'description', 'default_locale', 'locales', 'disk'];

    protected $hidden = ['deleted_at'];

    protected  static  function  boot(){
        parent::boot();

        static::creating(function  ($model)  {
            $model->uuid = (string) Str::uuid()->getHex();
        });
    }

    public function collections(){
        return $this->hasMany('App\Models\Collection')->orderBy('order', 'ASC');
    }

    public function fields(){
        return $this->hasMany('App\Models\CollectionField');
    }

    public function content(){
        return $this->hasMany('App\Models\Content');
    }
    
    public function meta(){
        return $this->hasMany('App\Models\ContentMeta');
    }
}
