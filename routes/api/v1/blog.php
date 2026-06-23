<?php

use App\Http\Controllers\Api\V1\Admin\AdminBlogPostController;
use App\Http\Controllers\Api\V1\Admin\BlogUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin/blog')->group(function () {
    Route::get   ('posts',                [AdminBlogPostController::class, 'index']);
    Route::post  ('posts',                [AdminBlogPostController::class, 'store']);
    Route::get   ('posts/{id}',           [AdminBlogPostController::class, 'show']);
    Route::put   ('posts/{id}',           [AdminBlogPostController::class, 'update']);
    Route::delete('posts/{id}',           [AdminBlogPostController::class, 'destroy']);
    Route::patch ('posts/{id}/publish',   [AdminBlogPostController::class, 'publish']);
    Route::patch ('posts/{id}/unpublish', [AdminBlogPostController::class, 'unpublish']);
    Route::post  ('uploads/presign',      [BlogUploadController::class,    'presign']);
});
