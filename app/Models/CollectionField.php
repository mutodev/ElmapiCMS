<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionField extends Model
{

    protected $table = 'collection_fields';

    protected $fillable = ['type', 'label', 'name', 'description', 'placeholder', 'options', 'validations', 'project_id', 'collection_id', 'order'];

    public function project(){
        return $this->belongsTo('App\Models\Project', 'project_id');
    }
    
    public function collection(){
        return $this->belongsTo('App\Models\Collection', 'collection_id');
    }
}
