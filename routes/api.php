<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\ProxyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\HomeController;

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

Route::get('/time', [HomeController::class, 'getSystemTime']);

// Users
Route::prefix('users')->group(function () {
    Route::get('/login', [AuthController::class, 'login']);
    Route::post('/register', [UserController::class, 'store']);
});

Route::get('settings/get-s3-api', [SettingController::class, 'getS3Setting']);
Route::get('settings/get-storage-type', [SettingController::class, 'getStorageTypeSetting']);
Route::get('settings/get-version', [SettingController::class, 'getPrivateServerVersion']); // 23.7.2024
Route::get('settings/get-setting', [SettingController::class, 'getAllSetting']); // 24.9.2024


Route::middleware(['auth:sanctum'])->group(function(){
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/update', [UserController::class, 'update']);
        Route::get('/current-user', [UserController::class, 'getCurrentUser']);
    });

    Route::prefix('groups')->group(function () {
        Route::get('/', [GroupController::class, 'index']);
        Route::get('/count', [GroupController::class, 'getTotal']);
        Route::post('/create', [GroupController::class, 'store']);
        Route::post('/update/{id}', [GroupController::class, 'update']);
        Route::get('/delete/{id}', [GroupController::class, 'destroy']);
        Route::get('/share/{id}', [GroupController::class, 'share']);
        Route::get('/shares/{id}', [GroupController::class, 'getGroupShares']);
    });

    Route::prefix('profiles')->group(function () {
        Route::get('/', [ProfileController::class, 'index']);
        Route::get('/count', [ProfileController::class, 'getTotal']);
        Route::get('/{id}', [ProfileController::class, 'show']);
        Route::post('/create', [ProfileController::class, 'store']);
        Route::post('/update/{id}', [ProfileController::class, 'update']);
        Route::get('/update-status/{id}', [ProfileController::class, 'updateStatus']);
        Route::get('/delete/{id}', [ProfileController::class, 'destroy']);
        Route::get('/share/{id}', [ProfileController::class, 'share']);
        Route::get('/shares/{id}', [ProfileController::class, 'getProfileShares']);
        Route::post('/start-using/{id}', [ProfileController::class, 'startUsing']);
        Route::post('/stop-using/{id}', [ProfileController::class, 'stopUsing']);
        Route::post('/add-tags/{id}', [ProfileController::class, 'addTags']);
        Route::post('/remove-tags/{id}', [ProfileController::class, 'removeTags']);
        Route::post('/restore/{id}', [ProfileController::class, 'restore']);
    });

    Route::prefix('settings')->group(function () {
        Route::get('/set-s3-api', [SettingController::class, 'setS3Setting']);
    });

    Route::post('file/upload', [UploadController::class, 'store']);
    Route::get('file/delete', [UploadController::class, 'delete']);
    Route::get('file/upload-s3', [UploadController::class, 'uploadS3']);
    
    Route::prefix('tags')->group(function () {
        Route::get('/', [TagController::class, 'index']);
        Route::get('/with-count', [TagController::class, 'getTagsWithCount']);
        Route::get('/{id}', [TagController::class, 'show']);
        Route::post('/create', [TagController::class, 'store']);
        Route::post('/update/{id}', [TagController::class, 'update']);
        Route::get('/delete/{id}', [TagController::class, 'destroy']);
    });

    Route::prefix('proxies')->group(function () {
        Route::get('/', [ProxyController::class, 'index']);
        Route::get('/{id}', [ProxyController::class, 'show']);
        Route::post('/create', [ProxyController::class, 'store']);
        Route::post('/update/{id}', [ProxyController::class, 'update']);
        Route::get('/delete/{id}', [ProxyController::class, 'destroy']);
        Route::post('/toggle-status/{id}', [ProxyController::class, 'toggleStatus']);
        Route::post('/add-tags/{id}', [ProxyController::class, 'addTags']);
        Route::post('/remove-tags/{id}', [ProxyController::class, 'removeTags']);
        Route::post('/test-connection/{id}', [ProxyController::class, 'testConnection']);
    });
});

