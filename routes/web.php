<?php

use App\Http\Controllers\ResultTemplateBuilderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Result Template Builder
|--------------------------------------------------------------------------
|
| Merge the block below into the existing routes/web.php of the STRIDE
| codebase. The prefix/middleware are a suggestion - adjust to match the
| existing auth and school-scoping middleware used by other builder pages.
|
*/

Route::middleware(['auth'])->prefix('builder')->name('result-templates.')->group(function () {
    Route::get('/result-templates', [ResultTemplateBuilderController::class, 'index'])->name('index');
    Route::get('/result-templates/create', [ResultTemplateBuilderController::class, 'create'])->name('create');
    Route::post('/result-templates', [ResultTemplateBuilderController::class, 'store'])->name('store');
    Route::get('/result-templates/{resultTemplate}', [ResultTemplateBuilderController::class, 'edit'])->name('edit');
    Route::put('/result-templates/{resultTemplate}', [ResultTemplateBuilderController::class, 'update'])->name('update');
    Route::delete('/result-templates/{resultTemplate}', [ResultTemplateBuilderController::class, 'destroy'])->name('destroy');
});
