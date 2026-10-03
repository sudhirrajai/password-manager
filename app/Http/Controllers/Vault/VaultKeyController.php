<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamVaultKey;
use App\Models\VaultAuditLog;
use App\Models\VaultItem;
use App\Models\VaultKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultKeyController extends Controller
{
    /**
     * Get vault keys for current user and current team.
     */
    public function show(Request $request, Team $current_team): JsonResponse
    {
        $user = $request->user();

        $userVaultKey = $user->vaultKey;
        $teamVaultKey = null;

        if (! $current_team->is_personal) {
            $teamVaultKey = TeamVaultKey::query()
                ->where('team_id', $current_team->id)
                ->where('user_id', $user->id)
                ->first();
        }

        return response()->json([
            'is_configured' => $userVaultKey !== null,
            'vault_salt' => $userVaultKey?->vault_salt,
            'encrypted_vault_key' => $userVaultKey?->encrypted_vault_key,
            'vault_key_iv' => $userVaultKey?->vault_key_iv,
            'has_recovery_key' => ! empty($userVaultKey?->encrypted_recovery_key),
            'recovery_salt' => $userVaultKey?->recovery_salt,
            'encrypted_recovery_key' => $userVaultKey?->encrypted_recovery_key,
            'recovery_key_iv' => $userVaultKey?->recovery_key_iv,
            'team_vault_key' => $teamVaultKey ? [
                'encrypted_team_key' => $teamVaultKey->encrypted_team_key,
                'team_key_iv' => $teamVaultKey->team_key_iv,
            ] : null,
            'is_team_vault' => ! $current_team->is_personal,
            'team_name' => $current_team->name,
        ]);
    }

    /**
     * Initialize or update user vault key and team vault keys.
     */
    public function store(Request $request, Team $current_team): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'vault_salt' => ['required', 'string', 'max:255'],
            'encrypted_vault_key' => ['required', 'string'],
            'vault_key_iv' => ['required', 'string', 'max:255'],
            'recovery_salt' => ['nullable', 'string', 'max:255'],
            'encrypted_recovery_key' => ['nullable', 'string'],
            'recovery_key_iv' => ['nullable', 'string', 'max:255'],
            'encrypted_team_key' => ['nullable', 'string'],
            'team_key_iv' => ['nullable', 'string', 'max:255'],
        ]);

        VaultKey::updateOrCreate(
            ['user_id' => $user->id],
            [
                'vault_salt' => $validated['vault_salt'],
                'encrypted_vault_key' => $validated['encrypted_vault_key'],
                'vault_key_iv' => $validated['vault_key_iv'],
                'recovery_salt' => $validated['recovery_salt'] ?? null,
                'encrypted_recovery_key' => $validated['encrypted_recovery_key'] ?? null,
                'recovery_key_iv' => $validated['recovery_key_iv'] ?? null,
            ]
        );

        if (! empty($validated['encrypted_team_key']) && ! empty($validated['team_key_iv'])) {
            TeamVaultKey::updateOrCreate(
                [
                    'team_id' => $current_team->id,
                    'user_id' => $user->id,
                ],
                [
                    'encrypted_team_key' => $validated['encrypted_team_key'],
                    'team_key_iv' => $validated['team_key_iv'],
                ]
            );
        }

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'action' => 'setup_vault_keys',
            'item_title' => 'Vault Security Setup',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Vault key initialized successfully.',
            'is_configured' => true,
        ]);
    }

    /**
     * Recover vault: re-encrypt UVK with new Master Password using verified Recovery Key.
     */
    public function recover(Request $request, Team $current_team): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'new_vault_salt' => ['required', 'string', 'max:255'],
            'new_encrypted_vault_key' => ['required', 'string'],
            'new_vault_key_iv' => ['required', 'string', 'max:255'],
            'new_recovery_salt' => ['required', 'string', 'max:255'],
            'new_encrypted_recovery_key' => ['required', 'string'],
            'new_recovery_key_iv' => ['required', 'string', 'max:255'],
        ]);

        $vaultKey = $user->vaultKey;
        abort_if(! $vaultKey, 404, 'Vault not initialized.');

        $vaultKey->update([
            'vault_salt' => $validated['new_vault_salt'],
            'encrypted_vault_key' => $validated['new_encrypted_vault_key'],
            'vault_key_iv' => $validated['new_vault_key_iv'],
            'recovery_salt' => $validated['new_recovery_salt'],
            'encrypted_recovery_key' => $validated['new_encrypted_recovery_key'],
            'recovery_key_iv' => $validated['new_recovery_key_iv'],
        ]);

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'action' => 'recovered_vault_password',
            'item_title' => 'Master Password Reset via Recovery Key',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Vault Master Password has been reset successfully using Recovery Key.',
            'is_configured' => true,
        ]);
    }

    /**
     * Full vault reset if user has lost all keys and wishes to wipe.
     */
    public function reset(Request $request, Team $current_team): JsonResponse
    {
        $user = $request->user();

        // Delete keys & personal items
        VaultKey::where('user_id', $user->id)->delete();
        TeamVaultKey::where('user_id', $user->id)->delete();
        VaultItem::where('user_id', $user->id)->where('team_id', $current_team->id)->forceDelete();

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'action' => 'wiped_vault_keys',
            'item_title' => 'Vault Reset',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Vault reset successfully.',
            'is_configured' => false,
        ]);
    }

    /**
     * Rotate or update the recovery key payload while vault is unlocked.
     */
    public function updateRecoveryKey(Request $request, Team $current_team): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'recovery_salt' => ['required', 'string', 'max:255'],
            'encrypted_recovery_key' => ['required', 'string'],
            'recovery_key_iv' => ['required', 'string', 'max:255'],
        ]);

        $vaultKey = $user->vaultKey;
        abort_if(! $vaultKey, 404, 'Vault not initialized.');

        $vaultKey->update([
            'recovery_salt' => $validated['recovery_salt'],
            'encrypted_recovery_key' => $validated['encrypted_recovery_key'],
            'recovery_key_iv' => $validated['recovery_key_iv'],
        ]);

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'action' => 'rotated_recovery_key',
            'item_title' => 'Emergency Recovery Key Updated',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Emergency Recovery Key updated successfully.',
            'has_recovery_key' => true,
        ]);
    }
}
