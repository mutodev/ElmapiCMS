<?php

namespace App\Http\Controllers\API;

use App\Models\Content;
use App\Models\Project;
use App\Models\Collection;
use App\Models\ContentMeta;
use Illuminate\Http\Request;
use App\Models\CollectionField;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\ContentResource;
use App\Http\Resources\ProjectResource;
use Illuminate\Support\Facades\Validator;

class ContentController extends Controller {
    
    /**
     * Get all content
     * 
     * @param string $uuid
     * @param string $slug
     * @param \Illuminate\Http\Request $request
     * @return \App\Http\Resources\ContentResource
     */
    public function getContent($uuid, $slug, Request $request){
        $auth = auth()->user();

        if(!$auth->tokenCan('read')) return response(['error' => 'API token does\'nt have the right permissions!'], 404);

        if($auth->uuid !== $uuid){
            return response(['error' => 'Project not found!'], 404);
        }

        $project = Project::find($auth->id);
        if(!$project) return response(['error' => 'Project not found!'], 404);

        $collection = Collection::where('project_id', $project->id)->where('slug', $slug)->first();
        if(!$collection) return response(['error' => 'Collection not found!'], 404);
        
        $content =  Content::with('meta')
                        ->where('project_id', $project->id)
                        ->where('collection_id', $collection->id);

        if($request->has('where')){
            $where = $request->get('where');
            if(!is_array($where)) return response(['error' => 'Incorrect where statement. See documentation: https://elmapicms.com/docs/content_api.html#where-clauses'], 422);
            
            if (!is_numeric(array_key_first($where)) || array_key_first($where) != 'or') {
                $multiDim = false;
            } else {
                $multiDim = true;
            }

            //Combining where clauses
            if($multiDim){
                $metaSql = 'SELECT c.id as content_id FROM content c,';

                $num = 1;
                foreach ($where as $w) {
                    $metaSql .= ' content_meta m'.$num.',';
                    $num++;
                }
                $metaSql = rtrim($metaSql, ',');
                $metaSql .= ' WHERE ';
                $num = 1;
                foreach ($where as $w) {
                    $metaSql .= ' m'.$num.".project_id='".$project->id."' AND m".$num.".collection_id='".$collection->id."' AND ";
                    $num++;
                }
                $metaSql = rtrim($metaSql, ' AND ');
                $num = 1;
                foreach ($where as $w) {
                    $metaSql .= ' AND c.id = m'.$num.'.content_id';
                    $num++;
                }
                $metaSql .= " AND (";
                $num = 1;
                foreach ($where as $k => $w) {
                    foreach ($w as $key => $value) {                        
                        if($key == 'id' || $key == 'locale' || $key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                            if(is_array($value)){
                                if(isset($value['not'])){
                                    $content = $content->where($key, '!=', $value['not']);
                                } elseif(isset($value['in'])){
                                    $content = $content->whereIn($key, explode(',', $value['in']));
                                } elseif(isset($value['not_in'])){
                                    $content = $content->whereNotIn($key, explode(',', $value['not_in']));
                                } elseif(isset($value['lt'])){
                                    if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                        $content = $content->whereDate($key, '<', $value['lt']);
                                    } else {
                                        $content = $content->where($key, '<', $value['lt']);
                                    }
                                } elseif(isset($value['lte'])){
                                    if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                        $content = $content->whereDate($key, '<=', $value['lte']);
                                    } else {
                                        $content = $content->where($key, '<=', $value['lte']);
                                    }
                                } elseif(isset($value['gt'])){
                                    if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                        $content = $content->whereDate($key, '>', $value['gt']);
                                    } else {
                                        $content = $content->where($key, '>', $value['gt']);
                                    }
                                } elseif(isset($value['gte'])){
                                    if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                        $content = $content->whereDate($key, '>=', $value['gte']);
                                    } else {
                                        $content = $content->where($key, '>=', $value['gte']);
                                    }
                                } elseif(isset($value['between'])){
                                    if(count(explode(',', $value['between'])) <= 1 || count(explode(',', $value['between'])) > 2) return response(['error' => 'Incorrect where statement'], 422);
    
                                    $content = $content->whereBetween($key, explode(',', $value['between']));
                                } elseif(isset($value['not_between'])){
                                    if(count(explode(',', $value['not_between'])) <= 1 || count(explode(',', $value['not_between'])) > 2) return response(['error' => 'Incorrect where statement'], 422);
    
                                    $content = $content->whereNotBetween($key, explode(',', $value['not_between']));
                                }
                            } else {
                                if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                    $content = $content->whereDate($key, $value);
                                } else {
                                    $content = $content->where($key, $value);
                                }
                            }
                        } else {
                            $bind[] = $key;
                            
                            if($num != 1 && $k == 'or'){
                                $metaSql .= " OR ";
                            }
                            if($num > 1 && $k != 'or'){
                                $metaSql .= " AND ";
                            }
                            
                            if(is_array($value)){
                                $metaSql .= "(m".$num.".field_name= ? AND ";
                                if(isset($value['like'])){
                                    $bind[] = "%$value[like]%";
                                    $metaSql .= "m".$num.".value LIKE ?)";
                                } elseif(isset($value['not'])){
                                    $bind[] = $value['not'];
                                    $metaSql .= "m".$num.".value != ?)";
                                } elseif(isset($value['in'])){
                                    $metaSql .= "m".$num.".value IN ( ".$value['in']." ))";
                                } elseif(isset($value['not_in'])){
                                    $metaSql .= "m".$num.".value NOT IN ( ".$value['not_in']." ))";
                                } elseif(isset($value['lt'])){
                                    $bind[] = $value['lt'];
                                    $metaSql .= "m".$num.".value < ?)";
                                } elseif(isset($value['lte'])){
                                    $bind[] = $value['lte'];
                                    $metaSql .= "m".$num.".value <= ?)";
                                } elseif(isset($value['gt'])){
                                    $bind[] = $value['gt'];
                                    $metaSql .= "m".$num.".value > ?)";
                                } elseif(isset($value['gte'])){
                                    $bind[] = $value['gte'];
                                    $metaSql .= "m".$num.".value >= ?)";
                                } elseif(isset($value['between'])){
                                    if(count(explode(',', $value['between'])) <= 1 || count(explode(',', $value['between'])) > 2) return response(['error' => 'Incorrect where statement'], 422);

                                    $expBetween = explode(',', $value['between']);
                                    $metaSql .= "m".$num.".value BETWEEN ".$expBetween[0]." AND ".$expBetween[1].")";
                                } elseif(isset($value['not_between'])){
                                    if(count(explode(',', $value['not_between'])) <= 1 || count(explode(',', $value['not_between'])) > 2) return response(['error' => 'Incorrect where statement'], 422);
                                    
                                    $expBetween = explode(',', $value['not_between']);
                                    $metaSql .= "m".$num.".value NOT BETWEEN ".$expBetween[0]." AND ".$expBetween[1].")";
                                }
                            } else {
                                if($value == 'null'){
                                    $meta = ContentMeta::where('project_id', $project->id)->where('collection_id', $collection->id)->where('field_name', $key)->where('value', '!=', '')->get(['content_id']);

                                    $in_str = "";
                                    foreach ($meta as $m) {
                                        $in_str .= $m->content_id.",";
                                    }
                                    $in_str = rtrim($in_str, ',');

                                    $metaSql .= "m".$num.".content_id NOT IN ( ".$in_str." )";

                                } elseif($value == 'not_null'){
                                    $meta = ContentMeta::where('project_id', $project->id)->where('collection_id', $collection->id)->where('field_name', $key)->where('value', '!=', '')->get(['content_id']);

                                    $in_str = "";
                                    foreach ($meta as $m) {
                                        $in_str .= $m->content_id.",";
                                    }
                                    $in_str = rtrim($in_str, ',');

                                    $metaSql .= "m".$num.".content_id IN ( ".$in_str." )";
                                } else {
                                    $field = CollectionField::where('project_id', $project->id)->where('collection_id', $collection->id)->where('name', $key)->first();

                                    if(!$field){
                                        return response(['error' => 'Field not found ['.$key.']'], 422);
                                    }
                                
                                    if($field->type == "relation"){
                                        $metaSql .= "(m".$num.".field_name= ? AND ";
                                        $metaSql .= "find_in_set('".$value."', cast(m".$num.".value as char)) > 0)";
                                    } else {
                                        $bind[] = $value;
                                        $metaSql .= "(m".$num.".field_name= ? AND ";
                                        $metaSql .= "m".$num.".value= ?)";
                                    }
                                }
                            }
                        }
                    }
                    $num++;
                }
                $metaSql .= ")";
                $num = 1;
                foreach ($where as $w) {
                    $metaSql .= " AND m".$num.".deleted_at is null";
                    $num++;
                }

                $query = DB::select($metaSql, $bind);

                $ids = [];
                foreach ($query as $q) {
                    $ids[] = $q->content_id;
                }

                $content =  $content->whereIn('id', $ids);
            } else {
                //Single Where
                $meta = ContentMeta::where('project_id', $project->id)->where('collection_id', $collection->id);
                foreach ($where as $key => $value) {
                    if($key == 'id' || $key == 'locale' || $key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                        if(is_array($value)){
                            if(isset($value['not'])){
                                $content = $content->where($key, '!=', $value['not']);
                            } elseif(isset($value['in'])){
                                $content = $content->whereIn($key, explode(',', $value['in']));
                            } elseif(isset($value['not_in'])){
                                $content = $content->whereNotIn($key, explode(',', $value['not_in']));
                            } elseif(isset($value['lt'])){
                                if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                    $content = $content->whereDate($key, '<', $value['lt']);
                                } else {
                                    $content = $content->where($key, '<', $value['lt']);
                                }
                            } elseif(isset($value['lte'])){
                                if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                    $content = $content->whereDate($key, '<=', $value['lte']);
                                } else {
                                    $content = $content->where($key, '<=', $value['lte']);
                                }
                            } elseif(isset($value['gt'])){
                                if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                    $content = $content->whereDate($key, '>', $value['gt']);
                                } else {
                                    $content = $content->where($key, '>', $value['gt']);
                                }
                            } elseif(isset($value['gte'])){
                                if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                    $content = $content->whereDate($key, '>=', $value['gte']);
                                } else {
                                    $content = $content->where($key, '>=', $value['gte']);
                                }
                            } elseif(isset($value['between'])){
                                if(count(explode(',', $value['between'])) <= 1 || count(explode(',', $value['between'])) > 2) return response(['error' => 'Incorrect where statement'], 422);

                                $content = $content->whereBetween($key, explode(',', $value['between']));
                            } elseif(isset($value['not_between'])){
                                if(count(explode(',', $value['not_between'])) <= 1 || count(explode(',', $value['not_between'])) > 2) return response(['error' => 'Incorrect where statement'], 422);

                                $content = $content->whereNotBetween($key, explode(',', $value['not_between']));
                            }
                        } else {
                            if($key == 'created_at' || $key == 'updated_at' || $key == 'published_at'){
                                $content = $content->whereDate($key, $value);
                            } else {
                                $content = $content->where($key, $value);
                            }
                        }
                    } else {
                        if(is_array($value)){
                            if(isset($value['like'])){
                                $meta = $meta->where('field_name', $key)->where('value', 'LIKE', "%$value[like]%");
                            } elseif(isset($value['not'])){
                                $meta = $meta->where('field_name', $key)->where('value', '!=', "$value[not]");
                            } elseif(isset($value['in'])){
                                $meta = $meta->where('field_name', $key)->whereIn('value', explode(',', $value['in']));
                            } elseif(isset($value['not_in'])){
                                $meta = $meta->where('field_name', $key)->whereNotIn('value', explode(',', $value['not_in']));
                            } elseif(isset($value['lt'])){
                                $meta = $meta->where('field_name', $key)->where('value', '<', $value['lt']);
                            } elseif(isset($value['lte'])){
                                $meta = $meta->where('field_name', $key)->where('value', '<=', $value['lte']);
                            } elseif(isset($value['gt'])){
                                $meta = $meta->where('field_name', $key)->where('value', '>', $value['gt']);
                            } elseif(isset($value['gte'])){
                                $meta = $meta->where('field_name', $key)->where('value', '>=', $value['gte']);
                            } elseif(isset($value['between'])){
                                if(count(explode(',', $value['between'])) <= 1 || count(explode(',', $value['between'])) > 2) return response(['error' => 'Incorrect where statement'], 422);

                                $meta = $meta->where('field_name', $key)->whereBetween('value', explode(',', $value['between']));
                            } elseif(isset($value['not_between'])){
                                if(count(explode(',', $value['not_between'])) <= 1 || count(explode(',', $value['not_between'])) > 2) return response(['error' => 'Incorrect where statement'], 422);

                                $meta = $meta->where('field_name', $key)->whereNotBetween('value', explode(',', $value['not_between']));
                            }
                        } else {
                            if($value == 'null'){
                                $copy = clone $meta;
                                $copy = $copy->where('field_name', $key)->where('value', '!=', '')->get(['content_id']);
                                $meta = $meta->whereNotIn('content_id', $copy);
                            } elseif($value == 'not_null'){
                                $copy = clone $meta;
                                $copy = $copy->where('field_name', $key)->where('value', '!=', '')->get(['content_id']);
                                $meta = $meta->whereIn('content_id', $copy);
                            } else {
                                $field = CollectionField::where('project_id', $project->id)->where('collection_id', $collection->id)->where('name', $key)->first();

                                if(!$field){
                                    return response(['error' => 'Field not found ['.$key.']'], 422);
                                }
                                
                                if($field->type == "relation"){
                                    $meta = $meta->where('field_name', $key)->whereRaw("find_in_set('".$value."', cast(value as char)) > 0");
                                } else {
                                    $meta = $meta->where('field_name', $key)->where('value', $value);
                                }
                            }
                        }
                    }
                }
                $meta = $meta->get(['content_id']);
                $content =  $content->whereIn('id', $meta);
            }
        }

        if($request->has('whereRelation')){
            $whereRelation = $request->get('whereRelation');
            if(!is_array($whereRelation)) return response(['error' => 'Incorrect whereRelation statement. See documentation: https://elmapicms.com/docs/content_api.html#where-through-relation'], 422);
            
            foreach ($whereRelation as $key => $value) {
                $mainField = CollectionField::where('project_id', $project->id)->where('collection_id', $collection->id)->where('name', $key)->first();

                if(!$mainField){
                    return response(['error' => 'Field not found ['.$key.']'], 422);
                }
                if($mainField->type !== "relation"){
                    return response(['error' => 'This field is not a relation type field.'], 422);
                }
                
                $relationOptions = json_decode($mainField->options);

                foreach($value as $rKey => $rValue){
                    $relationField = CollectionField::where('project_id', $project->id)->where('collection_id', $relationOptions->relation->collection)->where('name', $rKey)->first();

                    if(!$relationField){
                        return response(['error' => 'Relation field not found ['.$rKey.']'], 422);
                    }
                    
                    $relationMeta = ContentMeta::where('project_id', $project->id)->where('collection_id', $relationOptions->relation->collection)->where('field_name', $rKey)->where('value', 'LIKE', "%$rValue%")->first(['content_id']);

                    if(!$relationMeta){
                        return response(['error' => 'Record not found!'], 404);
                    }

                    $metaThroughRelation = ContentMeta::where('project_id', $project->id)->where('collection_id', $collection->id)->where('field_name', $key)->whereRaw("find_in_set('".$relationMeta->content_id."', cast(value as char)) > 0");
                    
                    $metaThroughRelation = $metaThroughRelation->get(['content_id']);

                    $content =  $content->whereIn('id', $metaThroughRelation);
                }
            }
        }

        if($request->has('sort')){
            $sortM = explode(',', $request->get('sort'));

            foreach ($sortM as $s) {
                $sort = explode(':', $s);
                if(count($sort) <= 1 || count($sort) > 2) return response(['error' => 'Incorrect sort statement'], 422);
                
                if($sort[0] == 'id' || $sort[0] == 'locale' || $sort[0] == 'created_at' || $sort[0] == 'updated_at' || $sort[0] == 'published_at'){
                    $content = $content->orderBy($sort[0], $sort[1]);
                } else {
                    $content = $content->orderBy(
                        ContentMeta::select('value')
                            ->whereColumn('content_meta.content_id', 'content.id')
                            ->where('field_name', $sort[0])
                            ->latest()
                            ->take(1),
                            $sort[1]
                    );
                }
            }
        }

        if($request->has('state')){
            if($request->get('state') == 'only_draft'){
                $content = $content->whereNull('published_at');
            }
        } else {
            $content = $content->whereNotNull('published_at');
        }

        if($request->has('offset') && !$request->has('limit')){
            return response(['error' => 'Incorrect offset statement. Offset must be used with limit. Documentation: https://elmapicms.com/docs/content_api.html#limit'], 422);
        }

        if($request->has('offset')){
            $content = $content->offset($request->get('offset'));
        }
        if($request->has('limit')){
            $content = $content->limit($request->get('limit'));
        }

        if($request->has('count')){
            return $content->count();
        } else {
            $selectFields = ['id', 'project_id', 'collection_id', 'locale'];
            
            if($request->has('timestamps')){
                $selectFields[] = 'created_at';
                $selectFields[] = 'updated_at';
                $selectFields[] = 'published_at';
            }
            $content = $content->select($selectFields);

            if($request->has('first')){
                $content = $content->first();
                if(!$content) return response(['error' => 'Not found!'], 404);

                return new ContentResource($content);
            } else {
                $content =  $content->get();
                return ContentResource::collection($content);
            }
        }
    }

    /**
     * Get content by id
     * 
     * @param string $uuid
     * @param string $slug
     * @param int $id
     * @return \App\Http\Resources\ContentResource
     */
    public function getContentByID($uuid, $slug, $id, Request $request){
        $auth = auth()->user();

        if(!$auth->tokenCan('read')) return response(['error' => 'API token does\'nt have the right permissions!'], 404);

        if($auth->uuid !== $uuid){
            return response(['error' => 'Project not found!'], 404);
        }

        $project = Project::find($auth->id);

        if(!$project) return response(['error' => 'Project not found!'], 404);

        $collection = Collection::where('project_id', $project->id)->where('slug', $slug)->first();

        if(!$collection) return response(['error' => 'Collection not found!'], 404);

        $content =  Content::with('meta')
                        ->where('project_id', $project->id)
                        ->where('collection_id', $collection->id)
                        ->whereNotNull('published_at');

        $selectFields = ['id', 'project_id', 'collection_id', 'locale'];

        if($request->has('timestamps')){
            $selectFields[] = 'created_at';
            $selectFields[] = 'updated_at';
            $selectFields[] = 'published_at';
        }
        $content = $content->select($selectFields)->find($id);

        if(!$content) return response(['error' => 'Not found!'], 404);
                        
        return new ContentResource($content);
    }

    /**
     * Create new content
     * 
     * @param string $uuid
     * @param string $slug
     * @param \Illuminate\Http\Request $request
     * @return \App\Http\Resources\ContentResource
     */
    public function create($uuid, $slug, Request $request){
        $auth = auth()->user();

        if(!$auth->tokenCan('create')) return response(['error' => 'API token does\'nt have the right permissions!'], 404);

        if($auth->uuid !== $uuid){
            return response(['error' => 'Project not found!'], 404);
        }

        $project = Project::find($auth->id);
        if(!$project) return response(['error' => 'Project not found!'], 404);

        $collection = Collection::with(['fields'])->where('project_id', $project->id)->where('slug', $slug)->first();
        if(!$collection) return response(['error' => 'Collection not found!'], 404);

        $rules = [];
        $messages = [];
        
        foreach ($collection->fields as $field) {
            $validations = json_decode($field->validations);
            if($validations->required->status){
                $rules[$field->name][] = 'required';
                $messages[$field->name.'.required'] = 'The '.$field->name.' field is required.';

                if($validations->required->message != null){
                    $messages[$field->name.'.required'] = $validations->required->message;
                }
            }

            if($field->type == "email"){
                $rules[$field->name][] = 'email';
                $messages[$field->name.'.email'] = 'The '.$field->name.' must be a valid email address.';
            }
            if($field->type == "number"){
                $rules[$field->name][] = 'numeric';
                $messages[$field->name.'.numeric'] = 'The '.$field->name.' must be numeric.';
            }

            if($validations->charcount->status){
                if($validations->charcount->type == "Between"){
                    $rules[$field->name][] = 'between:'.$validations->charcount->min.','.$validations->charcount->max;
                    $messages[$field->name.'.between'] = 'The '.$field->name.' must be between '.$validations->charcount->min.' and '.$validations->charcount->max;

                    if($field->type != 'number'){
                        $messages[$field->name.'.between'] .= ' characters.';
                    }
                } elseif($validations->charcount->type == "Min") {
                    $rules[$field->name][] = 'min:'.$validations->charcount->min;
                    $messages[$field->name.'.min'] = 'The '.$field->name.' must be at least '.$validations->charcount->min;

                    if($field->type != 'number'){
                        $messages[$field->name.'.min'] .= ' characters.';
                    }
                } elseif($validations->charcount->type == "Max") {
                    $rules[$field->name][] = 'max:'.$validations->charcount->max;
                    $messages[$field->name.'.max'] = 'The '.$field->name.' may not be greater than '.$validations->charcount->max;

                    if($field->type != 'number'){
                        $messages[$field->name.'.max'] .= ' characters.';
                    }
                }
            }
        }

        $validator = Validator::make($request->except(['locale']), $rules, $messages);
        $validator->validate();

        $uniqueErrors = [];
        
        foreach ($collection->fields as $field) {
            $validations = json_decode($field->validations);
            if($validations->unique->status){
                if($request->has($field->name)){
                    $data = ContentMeta::where('collection_id', $collection->id)->where('field_name', $field->name)->where('value', $request->get($field->name))->count();

                    if($data !== 0){
                        $uniqueErrors['errors'][$field->name] = ['The '.$field->name.' has already been taken.'];
                        
                        if($validations->unique->message != null){
                            $uniqueErrors['errors'][$field->name] = [$validations->unique->message];
                        }
                    }
                }
            }
        }
        if(count($uniqueErrors) !== 0){
            return response($uniqueErrors, 422);
        }

        $content = Content::create([
            'project_id' => $project->id,
            'collection_id' => $collection->id,
            'locale' => $request->has('locale') ? $request->get('locale') : $project->default_locale,
            'created_by' => null,
            'published_at' => $request->has('draft') && $request->get('draft') == 1 ? null : now(),
            'published_by' => null
        ]);


        $content_data = $request->all();
        
        foreach ($content_data as $key => $value) {
            $val = $value;

            if(!empty($value)){
                foreach ($collection->fields as $field) {
                    if($field->type == 'password' && $field->name == $key){
                        $val = Hash::make($value);
                    }
                    if($field->type == 'media' && $field->name == $key){
                        $str = '';
                        foreach ($value as $file) {
                            $str .= $file.',';
                        }
                        $val = rtrim($str, ',');
                    }
                    if($field->type == 'relation' && $field->name == $key){
                        $rl = explode(',', $value);
                        $str = '';
                        foreach ($rl as $relation) {
                            $str .= $relation.',';
                        }
                        $val = rtrim($str, ',');
                    }
                    if($field->type == 'json' && $field->name == $key){
                        $val = json_encode($value);
                    }
                }
                $content_meta = ContentMeta::create([
                    'project_id' => $project->id,
                    'collection_id' => $collection->id,
                    'content_id' => $content->id,
                    'field_name' => $key,
                    'value' => $val
                ]);
            }
        }

        return new ContentResource($content);
    }

    /**
     * Update a content
     * 
     * @param string $uuid
     * @param string $slug
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \App\Http\Resources\ContentResource
     */
    public function update($uuid, $slug, $id, Request $request){
        $auth = auth()->user();

        if(!$auth->tokenCan('update')) return response(['error' => 'API token does\'nt have the right permissions!'], 404);

        if($auth->uuid !== $uuid){
            return response(['error' => 'Project not found!'], 404);
        }

        $project = Project::find($auth->id);
        if(!$project) return response(['error' => 'Project not found!'], 404);

        $collection = Collection::with(['fields'])->where('project_id', $project->id)->where('slug', $slug)->first();
        if(!$collection) return response(['error' => 'Collection not found!'], 404);

        $content = Content::find($id);
        if(!$content) return response(['error' => 'Record not found!'], 404);

        $rules = [];
        $messages = [];

        foreach ($collection->fields as $field) {
            $validations = json_decode($field->validations);
            if($validations->required->status){
                $rules[$field->name][] = 'required';
                $messages[$field->name.'.required'] = 'The '.$field->name.' field is required.';

                if($validations->required->message != null){
                    $messages[$field->name.'.required'] = $validations->required->message;
                }
            }

            if($field->type == "email"){
                $rules[$field->name][] = 'email';
                $messages[$field->name.'.email'] = 'The '.$field->name.' must be a valid email address.';
            }
            if($field->type == "number"){
                $rules[$field->name][] = 'numeric';
                $messages[$field->name.'.numeric'] = 'The '.$field->name.' must be numeric.';
            }

            if($validations->charcount->status){
                if($validations->charcount->type == "Between"){
                    $rules[$field->name][] = 'between:'.$validations->charcount->min.','.$validations->charcount->max;
                    $messages[$field->name.'.between'] = 'The '.$field->name.' must be between '.$validations->charcount->min.' and '.$validations->charcount->max;

                    if($field->type != 'number'){
                        $messages[$field->name.'.between'] .= ' characters.';
                    }
                } elseif($validations->charcount->type == "Min") {
                    $rules[$field->name][] = 'min:'.$validations->charcount->min;
                    $messages[$field->name.'.min'] = 'The '.$field->name.' must be at least '.$validations->charcount->min;

                    if($field->type != 'number'){
                        $messages[$field->name.'.min'] .= ' characters.';
                    }
                } elseif($validations->charcount->type == "Max") {
                    $rules[$field->name][] = 'max:'.$validations->charcount->max;
                    $messages[$field->name.'.max'] = 'The '.$field->name.' may not be greater than '.$validations->charcount->max;

                    if($field->type != 'number'){
                        $messages[$field->name.'.max'] .= ' characters.';
                    }
                }
            }
        }

        $validator = Validator::make($request->all(), $rules, $messages);
        $validator->validate();

        $uniqueErrors = [];
        
        foreach ($collection->fields as $field) {
            $validations = json_decode($field->validations);
            if($validations->unique->status){
                if($request->has($field->name)){
                    $data = ContentMeta::where('content_id', '!=', $content->id)->where('collection_id', $collection->id)->where('field_name', $field->name)->where('value', $request->get($field->name))->count();

                    if($data !== 0){
                        $uniqueErrors['errors'][$field->name] = ['The '.$field->name.' has already been taken.'];
                        
                        if($validations->unique->message != null){
                            $uniqueErrors['errors'][$field->name] = [$validations->unique->message];
                        }
                    }
                }
            }
        }
        if(count($uniqueErrors) !== 0){
            return response($uniqueErrors, 422);
        }

        $content->update([
            'locale' => $request->get('locale'),
            'published_at' => $request->has('draft') && $request->get('draft') == 1 ? null : now(),
        ]);


        $content_data = $request->all();
        
        foreach ($content_data as $key => $value) {

            $val = $value;
            
            foreach ($collection->fields as $field) {
                if($field->type == 'password' && $field->name == $key){
                    $password = ContentMeta::where('content_id', $content->id)->where('field_name', $key)->first();

                    if(!$password){
                        $val = Hash::make($value);
                    } else {
                        if(empty($value)){
                            $val = $password->value;
                        } else {
                            $val = Hash::make($value);
                        }
                    }
                }
                if($field->type == 'media' && $field->name == $key){
                    $str = '';
                    foreach ($value as $file) {
                        $str .= $file.',';
                    }
                    $val = rtrim($str, ',');
                }
                if($field->type == 'relation' && $field->name == $key){
                    $rl = explode(',', $value);
                    $str = '';
                    foreach ($rl as $relation) {
                        $str .= $relation.',';
                    }
                    $val = rtrim($str, ',');
                }
                if($field->type == 'json' && $field->name == $key){
                    $val = json_encode($value);
                }
            }

            $content_meta = ContentMeta::where('content_id', $content->id)->where('field_name', $key)->first();
            
            if($content_meta){
                $content_meta->update([
                    'value' => $val
                ]);
            } else {
                if(!empty($value)){
                    $content_meta = ContentMeta::create([
                        'project_id' => $project->id,
                        'collection_id' => $collection->id,
                        'content_id' => $content->id,
                        'field_name' => $key,
                        'value' => $val
                    ]);
                }
            }
        }

        return new ContentResource($content);
    }

    /**
     * Delete a content
     * 
     * @param string $uuid
     * @param string $slug
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function delete($uuid, $slug, $id){
        $auth = auth()->user();

        if(!$auth->tokenCan('delete')) return response(['error' => 'API token does\'nt have the right permissions!'], 404);

        if($auth->uuid !== $uuid){
            return response(['error' => 'Project not found!'], 404);
        }

        $project = Project::find($auth->id);
        if(!$project) return response(['error' => 'Project not found!'], 404);

        $collection = Collection::with(['fields'])->where('project_id', $project->id)->where('slug', $slug)->first();
        if(!$collection) return response(['error' => 'Collection not found!'], 404);

        $content = Content::find($id);
        if(!$content) return response(['error' => 'Record not found!'], 404);

        $content->meta()->delete();

        if($content->delete()){
            return response(['message' => 'Record deleted.'], 200);
        } else {
            return response([], 404);
        }
    }
}