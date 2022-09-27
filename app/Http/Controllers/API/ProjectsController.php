<?php

namespace App\Http\Controllers\API;

use App\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;

class ProjectsController extends Controller {
    
    /** 
     * Get project
     * 
     * @param string $uuid
     * @return \App\Http\Resources\ProjectResource
     */
    public function show($uuid){
        $auth = auth()->user();

        if($auth->uuid !== $uuid){
            return response(['error' => 'Project not found!'], 404);
        }

        return new ProjectResource($auth);
    }
}