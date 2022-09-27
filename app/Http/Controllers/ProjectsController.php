<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use App\Models\Collection;
use Illuminate\Http\Request;
use App\Models\CollectionField;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Exceptions\UnauthorizedException;

class ProjectsController extends Controller
{
    
    /**
     * Get projects
     * 
     * @param \Illuminate\Http\Request $request
     * @return \App\Model\Project
     */
    public function index(Request $request){
        $user = auth()->user();
        
        $projects = Project::when($request->get('search'), function($q)use($request){
            $searchItem = $request->get('search');
            return $q->where('name', 'LIKE', "%$searchItem%");
        });

        if(!$user->isSuperAdmin()){
            $roles = $user->roles;

            $arr = [];
            
            foreach ($roles as $role) {
                $ex = explode('admin', $role->name);

                if(isset($ex[1]) && !in_array($ex[1], $arr))
                    $arr[] = $ex[1];
                
                $ex = explode('editor', $role->name);

                if(isset($ex[1]) && !in_array($ex[1], $arr))
                    $arr[] = $ex[1];
            }

            $projects = $projects->whereIn('id', $arr);
        }

        $projects = $projects->orderBy('created_at', 'DESC')->get();

        return response($projects, 200);
    }
    
    /**
     * Create a new project
     * 
     * @param \Illuminate\Http\Request $request
     * @return \App\Model\Project
     */
    public function store(Request $request){

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/[#$%^&*()+=\-\[\]\';,\/{}|":<>?~\\\\]/'],
            'default_locale' => 'required|max:255',
        ]);

        $project = Project::create([
        	'name' => $request->get('name'),
        	'description' => $request->get('description'),
        	'default_locale' => $request->get('default_locale'),
        	'locales' => $request->get('default_locale'),
        ]);

        Role::create(['name' => 'admin'.$project->id]);
        Role::create(['name' => 'editor'.$project->id]);

        if($request->get('type') == 2){
            $pages = Collection::create(['name' => 'Pages', 'slug' => 'pages', 'project_id' => $project->id, 'order' => 1]);
            $posts = Collection::create(['name' => 'Posts', 'slug' => 'posts', 'project_id' => $project->id, 'order' => 2]);
            $categories = Collection::create(['name' => 'Categories', 'slug' => 'categories', 'project_id' => $project->id, 'order' => 3]);
            $authors = Collection::create(['name' => 'Authors', 'slug' => 'authors', 'project_id' => $project->id, 'order' => 4]);
            $tags = Collection::create(['name' => 'Tags', 'slug' => 'tags', 'project_id' => $project->id, 'order' => 5]);
            $comments = Collection::create(['name' => 'Comments', 'slug' => 'comments', 'project_id' => $project->id, 'order' => 6]);
            $globals = Collection::create(['name' => 'Globals', 'slug' => 'globals', 'project_id' => $project->id, 'order' => 7]);

            $pages_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $pages->id, 'order' => 1,
                'type' => 'text', 'label' => 'Title', 'name' => 'title', 'options' => '{"slug": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false, "message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $pages_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $pages->id, 'order' => 2,
                'type' => 'slug', 'label' => 'URL', 'name' => 'url', 'options' => '{"slug": {"field": "title"},"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": true,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $pages_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $pages->id, 'order' => 3,
                'type' => 'richtext', 'label' => 'Content', 'name' => 'content', 'options' => '{"slug": [],"media": [], "relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $pages_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $pages->id, 'order' => 4,
                'type' => 'enumeration', 'label' => 'Menu Position', 'name' => 'menu-position', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": ["MainMenu","FooterMenu","Both"],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);

            $posts_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $posts->id, 'order' => 1,
                'type' => 'text', 'label' => 'Title', 'name' => 'title', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $posts_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $posts->id, 'order' => 2,
                'type' => 'slug', 'label' => 'URL', 'name' => 'url', 'options' => '{"slug": {"field": "title"},"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": true,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $posts_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $posts->id, 'order' => 3,
                'type' => 'longtext', 'label' => 'Excerpt', 'name' => 'excerpt', 'options' => '{"slug": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $posts_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $posts->id, 'order' => 4,
                'type' => 'richtext', 'label' => 'Content', 'name' => 'content', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $posts_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $posts->id, 'order' => 5,
                'type' => 'media', 'label' => 'Cover Image', 'name' => 'cover-image', 'options' => '{"slug": [],"media": {"type": 1},"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $posts_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $posts->id, 'order' => 6,
                'type' => 'relation', 'label' => 'Category', 'name' => 'category', 'options' => '{"slug": [],"media": [],"relation": {"type": "1","collection": '.$categories->id.'},"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $posts_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $posts->id, 'order' => 7,
                'type' => 'relation', 'label' => 'Author', 'name' => 'author', 'options' => '{"slug": [],"relation": {"type": 1,"collection": '.$authors->id.'},"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $posts_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $posts->id, 'order' => 8,
                'type' => 'relation', 'label' => 'Tags', 'name' => 'tags', 'options' => '{"slug": [],"media": [],"relation": {"type": "2","collection": '.$tags->id.'},"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);

            $categories_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $categories->id, 'order' => 1,
                'type' => 'text', 'label' => 'Title', 'name' => 'title', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $categories_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $categories->id, 'order' => 1,
                'type' => 'slug', 'label' => 'URL', 'name' => 'url', 'options' => '{"slug": {"field": "title"},"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": true,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            
            $authors_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $authors->id, 'order' => 1,
                'type' => 'text', 'label' => 'Name', 'name' => 'name', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $authors_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $authors->id, 'order' => 1,
                'type' => 'longtext', 'label' => 'Info', 'name' => 'info', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $authors_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $authors->id, 'order' => 1,
                'type' => 'media', 'label' => 'Avatar', 'name' => 'avatar', 'options' => '{"slug": [],"media": {"type": 1},"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $authors_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $authors->id, 'order' => 1,
                'type' => 'text', 'label' => 'Facebook', 'name' => 'facebook', 'options' => '{"slug": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $authors_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $authors->id, 'order' => 1,
                'type' => 'text', 'label' => 'Instagram', 'name' => 'instagram', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $authors_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $authors->id, 'order' => 1,
                'type' => 'text', 'label' => 'Twitter', 'name' => 'twitter', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $authors_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $authors->id, 'order' => 1,
                'type' => 'text', 'label' => 'Linkedin', 'name' => 'linkedin', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            
            $tags_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $tags->id, 'order' => 1,
                'type' => 'text', 'label' => 'Tag', 'name' => 'tag', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            
            $comments_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $comments->id, 'order' => 1,
                'type' => 'text', 'label' => 'Name', 'name' => 'name', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $comments_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $comments->id, 'order' => 1,
                'type' => 'email', 'label' => 'E-mail', 'name' => 'e-mail', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $comments_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $comments->id, 'order' => 1,
                'type' => 'longtext', 'label' => 'Comment', 'name' => 'comment', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $comments_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $comments->id, 'order' => 1,
                'type' => 'relation', 'label' => 'Post', 'name' => 'post', 'options' => '{"slug": [],"relation": {"type": 1,"collection": '.$posts->id.'},"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": false,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);

            $globals_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $globals->id, 'order' => 1,
                'type' => 'slug', 'label' => 'Label', 'name' => 'label', 'options' => '{"slug": {"field": null},"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": true,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
            $globals_field = CollectionField::create(['project_id' => $project->id, 'collection_id' => $globals->id, 'order' => 1,
                'type' => 'text', 'label' => 'Value', 'name' => 'value', 'options' => '{"slug": [],"media": [],"relation": [],"enumeration": [],"hideInContentList": false}', 'validations' => '{"unique": {"status": false,"message": null},"required": {"status": true,"message": null},"charcount": {"max": null,"min": null,"type": "Between","status": false,"message": null}}',]);
          
            
        }

        return response($project, 200);
    }

    /**
     * Get project by id
     * 
     * @param int $id
     * @return \App\Models\Project
     */
    public function show($id){
        $user = auth()->user();

        if(!$user->isSuperAdmin() && !$user->hasRole('admin'.$id) && !$user->hasRole('editor'.$id)){
            throw UnauthorizedException::forRoles(['admin'.$id]);
        }

        $project = Project::with('collections')->findOrFail($id);
        
        $project->s3 = false;
        //Check if AWS S3 has been configured
        if(config('filesystems.disks.s3.key') && config('filesystems.disks.s3.secret') && config('filesystems.disks.s3.region') && config('filesystems.disks.s3.bucket')){
            $project->s3 = true;
        }

        return $project;
    }

    /**
     * Update project
     * 
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\Project
     */
    public function update($id, Request $request){
        $user = auth()->user();

        if(!$user->isSuperAdmin() && !$user->hasRole('admin'.$id)){
            throw UnauthorizedException::forRoles(['admin'.$id]);
        }

        $project = Project::findOrFail($id);

        $request->validate([
            'name' => 'required|max:255',
        ],[
            'name.required' => __('Project name is required')
        ]);

        $project->update([
        	'name' => $request->get('name'),
        	'description' => $request->get('description'),
        	'disk' => $request->get('disk')
        ]);

        return response($project, 200);
    }

    /** 
     * Delete a project
     * 
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id){
        $user = auth()->user();

        if(!$user->isSuperAdmin()){
            throw UnauthorizedException::forRoles(['admin'.$id]);
        }

        $project = Project::findOrFail($id);

        $project->collections()->delete();
        $project->fields()->delete();
        $project->content()->forceDelete();
        $project->meta()->forceDelete();

        $admin_role = Role::where('name', 'admin'.$id)->delete();
        $editor_role = Role::where('name', 'editor'.$id)->delete();

        if($project->delete()){
            return response([], 200);
        } else {
            return response([], 404);
        }
    }
    
    /** 
     * Get project locales
     * 
     * @param int $id
     * @return \App\Models\Project
     */
    public function locales($id){
        return Project::findOrFail($id);
    }

    /**
     * Add new locale to project
     * 
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function addLocale($id, Request $request){
        $project = Project::findOrFail($id);

        $project_locales = explode(',', $project->locales);

        if(in_array($request->get('locale'), $project_locales)){
            return response([], 422);
        }

        if(!in_array($request->get('locale'), $project_locales)){
            if($project->locales === null){
                $project->locales = $request->get('locale');
            } else {
                $project->locales = $project->locales.",".$request->get('locale');
            }
        }

        $project->save();
    }

    /**
     * Change default locale of the project
     * 
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function changeDefaultLocale($id, Request $request){
        $project = Project::findOrFail($id);
        
        $project->default_locale = $request->get('locale');
        $project->save();
    }
    
    /** 
     * Delete a locale from project
     * 
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function deleteLocale($id, Request $request){
        $project = Project::findOrFail($id);

        if($request->get('locale') == $project->default_locale){
            return response([], 422);
        }

        $project_locales = explode(',', $project->locales);

        $localesStr = '';
        foreach ($project_locales as $locale) {
            if($locale != $request->get('locale')){
                $localesStr .= $locale.',';
            }
        }
        $project->locales = rtrim($localesStr, ',');
        $project->save();
    }

    /** 
     * Get users
     * 
     * @param int $id
     * @return mixed
     */
    public function users($id){
        $project = Project::findOrFail($id);

        $super_admins = User::whereHas('roles', function($q){ $q->where('name', 'super_admin'); })->get();
        $users = User::whereDoesntHave('roles', function($q){ $q->where('name', 'super_admin'); })->get();

        $admins = User::whereHas('roles', function($q)use($project){ $q->where('name', 'admin'.$project->id); })->get();
        $editors = User::whereHas('roles', function($q)use($project){ $q->where('name', 'editor'.$project->id); })->get();

        $data['project'] = $project;
        $data['super_admins'] = $super_admins;
        $data['admins'] = $admins;
        $data['editors'] = $editors;
        $data['users'] = $users;

        return $data;
    }

    /** 
     * Assign user to the project
     * 
     * @param int id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function assignUser($id, Request $request){
        $project = Project::findOrFail($id);

        $user = User::findOrFail($request->get('user_id'));

        $role = Role::where('name', $request->get('role').$project->id)->first();

        if($role){
            $user->assignRole($role);
        } else {
            $admin = Role::create(['name' => 'admin'.$project->id]);
            $editor = Role::create(['name' => 'editor'.$project->id]);

            $role = Role::where('name', $request->get('role').$project->id)->first();
            $user->assignRole($role);
        }
    }
    
    /**
     * Remove user from project
     * 
     * @param int id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function removeUser($id, Request $request){
        $project = Project::findOrFail($id);

        $user = User::findOrFail($request->get('user_id'));

        $role = Role::where('name', $request->get('role').$project->id)->first();

        if($user->hasRole($role)){
            $user->removeRole($role);

            return response([], 200);
        } else {
            return response([], 404);
        }
    }
    
    /**
     * Create new user
     * 
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function newUser($id, Request $request){
        $project = Project::findOrFail($id);

        $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        $user = User::create([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'password' => Hash::make($request->get('password'))
        ]);
    }

    /**
     * Get api settings
     * 
     * @param int $id
     * @return mixed
     */
    public function api($id){
        $project = Project::findOrFail($id);

        $data['project'] = $project;
        $data['tokens'] = $project->tokens;

        return $data;
    }

    /**
     * Crate a new API token
     * 
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return string $token->plainTextToken
     */
    public function newToken($id, Request $request){
        $project = Project::findOrFail($id);

        $request->validate([
            'name' => 'required'
        ]);

        $token = $project->createToken(
            $request->get('name'),
            $request->input('permissions', [])
        );

        return explode('|', $token->plainTextToken, 2)[1];
    }

    /**
     * Update a token
     * 
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function updateToken($id, Request $request){
        $project = Project::findOrFail($id);
    
        $token_id = $request->get('id');
        $token = PersonalAccessToken::findOrFail($token_id);

        $request->validate([
            'name' => 'required'
        ]);

        $token->update([
            'name' => $request->get('name'),
            'abilities' => $request->get('permissions'),
        ]);
    }

    /**
     * Delete token
     * 
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function deleteToken($id, Request $request){
        $project = Project::findOrFail($id);

        $project->tokens()->where('id', $request->get('id'))->delete();
    }
}