<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\MediaController;
use App\Http\Controllers\API\ContentController;
use App\Http\Controllers\API\ProjectsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->group(function(){
    Route::get('/{uuid}/project-media', [MediaController::class, 'getProjectMedia']);
    Route::get('/{uuid}/project-media/{id}', [MediaController::class, 'getFileByID']);
    Route::get('/{uuid}/project-media/name/{name}', [MediaController::class, 'getFileByName']);
    Route::delete('/{uuid}/project-media/{id}', [MediaController::class, 'deleteFile']);
    Route::post('/{uuid}/project-media/upload', [MediaController::class, 'uploadFile']);

    Route::get('/{uuid}', [ProjectsController::class, 'show']);

    Route::get('/{uuid}/{slug}', [ContentController::class, 'getContent']);
    Route::get('/{uuid}/{slug}/{id}', [ContentController::class, 'getContentByID']);
    Route::post('/{uuid}/{slug}', [ContentController::class, 'create']);
    Route::post('/{uuid}/{slug}/update/{id}', [ContentController::class, 'update']);
    Route::delete('/{uuid}/{slug}/{id}', [ContentController::class, 'delete']);

    
});