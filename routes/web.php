<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\AiMemoryController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ExportQueueController;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\QueryHistoryController;
use App\Http\Controllers\SavedQueryController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('chat');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('chat', ChatController::class)->name('chat');
    Route::redirect('dashboard', '/chat')->name('dashboard');

    Route::get('connections', [ConnectionController::class, 'index'])
        ->middleware('monitor.permission:connections.view')
        ->name('connections.index');
    Route::post('connections', [ConnectionController::class, 'store'])
        ->middleware('monitor.permission:connections.create')
        ->name('connections.store');
    Route::get('connections/{connection}', [ConnectionController::class, 'show'])
        ->middleware('monitor.permission:connections.view')
        ->name('connections.show');
    Route::put('connections/{connection}', [ConnectionController::class, 'update'])
        ->middleware('monitor.permission:connections.update')
        ->name('connections.update');
    Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])
        ->middleware('monitor.permission:connections.delete')
        ->name('connections.destroy');
    Route::post('connections/{connection}/test', [ConnectionController::class, 'test'])
        ->middleware('monitor.permission:connections.update')
        ->name('connections.test');

    Route::get('connections/{connection}/schemas', [ConnectionController::class, 'schemas'])
        ->middleware('monitor.permission:schemas.view')
        ->name('connections.schemas');
    Route::get('connections/{connection}/tables', [ConnectionController::class, 'tables'])
        ->middleware('monitor.permission:tables.view')
        ->name('connections.tables');
    Route::get('connections/{connection}/tables/{table}', [ConnectionController::class, 'table'])
        ->middleware('monitor.permission:tables.view')
        ->name('connections.tables.show');
    Route::get('connections/{connection}/tables/{table}/preview', [ConnectionController::class, 'preview'])
        ->middleware('monitor.permission:tables.view')
        ->name('connections.tables.preview');

    Route::post('queries/validate', [QueryController::class, 'validate'])
        ->middleware(['monitor.permission:queries.execute', 'throttle:monitor-sql-validate'])
        ->name('queries.validate');
    Route::post('queries/execute', [QueryController::class, 'execute'])
        ->middleware(['monitor.permission:queries.execute', 'throttle:monitor-sql-execute'])
        ->name('queries.execute');
    Route::post('queries/ai-generate', [QueryController::class, 'aiGenerate'])
        ->middleware(['monitor.permission:queries.ai_generate', 'throttle:monitor-sql-ai-generate'])
        ->name('queries.ai-generate');
    Route::post('queries/save', [QueryController::class, 'save'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.save');

    Route::get('queries/history', [QueryHistoryController::class, 'index'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.history.index');
    Route::get('queries/history/{id}', [QueryHistoryController::class, 'show'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.history.show');
    Route::put('queries/history/{id}', [QueryHistoryController::class, 'update'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.history.update');
    Route::delete('queries/history/{id}', [QueryHistoryController::class, 'destroy'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.history.destroy');

    Route::get('queries/saved', [SavedQueryController::class, 'index'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.saved.index');
    Route::post('queries/saved', [SavedQueryController::class, 'store'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.saved.store');
    Route::put('queries/saved/{id}', [SavedQueryController::class, 'update'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.saved.update');
    Route::delete('queries/saved/{id}', [SavedQueryController::class, 'destroy'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.saved.destroy');

    Route::get('conversations', [ConversationController::class, 'index'])
        ->middleware('monitor.permission:queries.ai_generate')
        ->name('conversations.index');
    Route::get('conversations/{id}', [ConversationController::class, 'show'])
        ->middleware('monitor.permission:queries.ai_generate')
        ->name('conversations.show');
    Route::put('conversations/{id}', [ConversationController::class, 'update'])
        ->middleware('monitor.permission:queries.ai_generate')
        ->name('conversations.update');
    Route::delete('conversations/{id}', [ConversationController::class, 'destroy'])
        ->middleware('monitor.permission:queries.ai_generate')
        ->name('conversations.destroy');

    Route::get('ai-memory', [AiMemoryController::class, 'index'])
        ->middleware('monitor.permission:queries.ai_generate')
        ->name('ai-memory.index');
    Route::delete('ai-memory/{id}', [AiMemoryController::class, 'destroy'])
        ->middleware('monitor.permission:queries.ai_generate')
        ->name('ai-memory.destroy');
    Route::post('ai-memory/clear-all', [AiMemoryController::class, 'clearAll'])
        ->middleware('monitor.permission:queries.ai_generate')
        ->name('ai-memory.clear-all');

    Route::post('exports', [ExportController::class, 'store'])
        ->middleware('monitor.permission:queries.export')
        ->name('exports.store');
    Route::get('exports', [ExportController::class, 'index'])
        ->middleware('monitor.permission:queries.export')
        ->name('exports.index');
    Route::get('exports/{export}/download', [ExportController::class, 'download'])
        ->middleware('monitor.permission:queries.export')
        ->name('exports.download');
    Route::get('exports/queue', ExportQueueController::class)
        ->middleware('monitor.permission:queries.export')
        ->name('exports.queue');

    Route::get('audit', [AuditController::class, 'index'])
        ->middleware('monitor.permission:audit.view')
        ->name('audit.index');

    Route::get('admin/access-control', AccessControlController::class)
        ->middleware('monitor.permission:connections.create')
        ->name('admin.access-control');

    Route::post('admin/users', [AccessControlController::class, 'storeUser'])
        ->middleware('monitor.permission:connections.create')
        ->name('admin.users.store');
    Route::put('admin/users/{user}', [AccessControlController::class, 'updateUser'])
        ->middleware('monitor.permission:connections.create')
        ->name('admin.users.update');
    Route::delete('admin/users/{user}', [AccessControlController::class, 'destroyUser'])
        ->middleware('monitor.permission:connections.create')
        ->name('admin.users.destroy');
    Route::post('admin/users/{user}/roles', [AccessControlController::class, 'syncUserRoles'])
        ->middleware('monitor.permission:connections.create')
        ->name('admin.users.roles');

    Route::post('admin/roles', [AccessControlController::class, 'storeRole'])
        ->middleware('monitor.permission:connections.create')
        ->name('admin.roles.store');
    Route::put('admin/roles/{role}', [AccessControlController::class, 'updateRole'])
        ->middleware('monitor.permission:connections.create')
        ->name('admin.roles.update');
    Route::delete('admin/roles/{role}', [AccessControlController::class, 'destroyRole'])
        ->middleware('monitor.permission:connections.create')
        ->name('admin.roles.destroy');
    Route::post('admin/roles/{role}/permissions', [AccessControlController::class, 'syncRolePermissions'])
        ->middleware('monitor.permission:connections.create')
        ->name('admin.roles.permissions');

    Route::get('backups', BackupController::class)
        ->middleware('monitor.permission:connections.create')
        ->name('backups.index');

    Route::post('queries/ai-summary', [QueryController::class, 'aiSummary'])
        ->middleware(['monitor.permission:queries.ai_generate', 'throttle:monitor-sql-ai-generate'])
        ->name('queries.ai-summary');

    Route::get('queries/favorites', [QueryController::class, 'favorites'])
        ->middleware('monitor.permission:queries.execute')
        ->name('queries.favorites');
});

require __DIR__.'/settings.php';
