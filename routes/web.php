<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Vault\SecretLinkController;
use App\Http\Controllers\Vault\VaultAuditLogController;
use App\Http\Controllers\Vault\VaultItemController;
use App\Http\Controllers\Vault\VaultKeyController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Direct /dashboard redirect to current team slug
Route::middleware(['auth'])->get('/dashboard', function (Request $request) {
    $team = $request->user()->currentTeam ?? $request->user()->personalTeam();
    if (! $team) {
        abort(404);
    }

    return redirect()->route('dashboard', $team->slug);
});

// Public secret viewing route (Zero-knowledge recipient decrypt page)
Route::inertia('share/{token}', 'ShareView')->name('secret.view');
Route::match(['get', 'post'], 'api/secret/{token}', [SecretLinkController::class, 'publicShow'])->name('api.secret.show');

// Authenticated Team / Personal Vault Routes
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Vault Key Management (Master Key & Team Keys)
        Route::get('vault/key', [VaultKeyController::class, 'show'])->name('vault.key.show');
        Route::post('vault/key', [VaultKeyController::class, 'store'])->name('vault.key.store');
        Route::post('vault/key/recover', [VaultKeyController::class, 'recover'])->name('vault.key.recover');
        Route::post('vault/key/reset', [VaultKeyController::class, 'reset'])->name('vault.key.reset');
        Route::post('vault/key/recovery-key', [VaultKeyController::class, 'updateRecoveryKey'])->name('vault.key.rotate-recovery');

        // Vault Items CRUD
        Route::get('vault/items', [VaultItemController::class, 'index'])->name('vault.items.index');
        Route::post('vault/items', [VaultItemController::class, 'store'])->name('vault.items.store');
        Route::put('vault/items/{item}', [VaultItemController::class, 'update'])->name('vault.items.update');
        Route::delete('vault/items/{item}', [VaultItemController::class, 'destroy'])->name('vault.items.destroy');
        Route::post('vault/items/{id}/restore', [VaultItemController::class, 'restore'])->name('vault.items.restore');
        Route::delete('vault/items/{id}/force', [VaultItemController::class, 'forceDelete'])->name('vault.items.force');
        Route::get('vault/items/{item}/history', [VaultItemController::class, 'history'])->name('vault.items.history');
        Route::post('vault/items/{item}/history/{history}/restore', [VaultItemController::class, 'restoreVersion'])->name('vault.items.history.restore');
        Route::post('vault/items/{item}/favorite', [VaultItemController::class, 'toggleFavorite'])->name('vault.items.favorite');
        Route::post('vault/items/audit', [VaultItemController::class, 'audit'])->name('vault.items.audit');

        // Secret Links (Ephemeral self-destructing links)
        Route::get('vault/shares', [SecretLinkController::class, 'index'])->name('vault.shares.index');
        Route::post('vault/shares', [SecretLinkController::class, 'store'])->name('vault.shares.store');
        Route::delete('vault/shares/{secretLink}', [SecretLinkController::class, 'destroy'])->name('vault.shares.destroy');

        // Audit Logs
        Route::get('vault/audit-logs', [VaultAuditLogController::class, 'index'])->name('vault.audit.index');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
