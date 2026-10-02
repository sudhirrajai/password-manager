<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamVaultKey;
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
            'encrypted_team_key' => ['nullable', 'string'],
            'team_key_iv' => ['nullable', 'string', 'max:255'],
        ]);

        VaultKey::updateOrCreate(
            ['user_id' => $user->id],
            [
                'vault_salt' => $validated['vault_salt'],
                'encrypted_vault_key' => $validated['encrypted_vault_key'],
                'vault_key_iv' => $validated['vault_key_iv'],
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

        return response()->json([
            'message' => 'Vault key initialized successfully.',
            'is_configured' => true,
        ]);
    }
}
