<?php

namespace App\Http\Controllers\Vault;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\VaultAuditLog;
use App\Models\VaultItem;
use App\Models\VaultItemHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultItemController extends Controller
{
    /**
     * List vault items for the current team.
     */
    public function index(Request $request, Team $current_team): JsonResponse
    {
        if ($request->boolean('trash')) {
            abort_unless(
                $this->canManageTrash($request, $current_team),
                403,
                'Trash access is restricted to Administrators and Owners.',
            );
        }

        $query = VaultItem::query()
            ->where('team_id', $current_team->id)
            ->with('user:id,name,email');

        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->boolean('favorite')) {
            $query->where('is_favorite', true);
        }

        $items = $query->latest('updated_at')->get();

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * Store a newly created encrypted vault item.
     */
    public function store(Request $request, Team $current_team): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:login,card,note,server'],
            'title' => ['required', 'string'],
            'encrypted_data' => ['required', 'string'],
            'iv' => ['required', 'string', 'max:255'],
            'is_favorite' => ['boolean'],
            'folder' => ['nullable', 'string', 'max:100'],
        ]);

        $item = VaultItem::create([
            'team_id' => $current_team->id,
            'user_id' => $user->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'encrypted_data' => $validated['encrypted_data'],
            'iv' => $validated['iv'],
            'is_favorite' => $validated['is_favorite'] ?? false,
            'folder' => $validated['folder'] ?? null,
            'password_updated_at' => now(),
        ]);

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'vault_item_id' => $item->id,
            'action' => 'created',
            'item_title' => $item->title,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Vault item created securely.',
            'item' => $item->load('user:id,name,email'),
        ], 201);
    }

    /**
     * Update an existing encrypted vault item.
     */
    public function update(Request $request, Team $current_team, VaultItem $item): JsonResponse
    {
        abort_if($item->team_id !== $current_team->id, 404);

        $user = $request->user();

        $validated = $request->validate([
            'type' => ['sometimes', 'required', 'string', 'in:login,card,note,server'],
            'title' => ['sometimes', 'required', 'string'],
            'encrypted_data' => ['sometimes', 'required', 'string'],
            'iv' => ['sometimes', 'required', 'string', 'max:255'],
            'is_favorite' => ['boolean'],
            'folder' => ['nullable', 'string', 'max:100'],
        ]);

        $dataChanged = isset($validated['encrypted_data']) && (
            $validated['encrypted_data'] !== $item->encrypted_data ||
            ($validated['iv'] ?? null) !== $item->iv
        );

        if ($dataChanged) {
            $item->histories()->create([
                'user_id' => $user->id,
                'encrypted_data' => $item->encrypted_data,
                'iv' => $item->iv,
            ]);
        }

        $item->update(array_merge($validated, [
            'password_updated_at' => $dataChanged ? now() : $item->password_updated_at,
        ]));

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'vault_item_id' => $item->id,
            'action' => 'updated',
            'item_title' => $item->title,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Vault item updated securely.',
            'item' => $item->load('user:id,name,email'),
        ]);
    }

    /**
     * Soft delete (move to trash).
     */
    public function destroy(Request $request, Team $current_team, VaultItem $item): JsonResponse
    {
        abort_if($item->team_id !== $current_team->id, 404);

        $user = $request->user();
        $title = $item->title;

        $item->delete();

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'vault_item_id' => $item->id,
            'action' => 'deleted',
            'item_title' => $title,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Item moved to trash.',
        ]);
    }

    /**
     * Restore item from trash.
     */
    public function restore(Request $request, Team $current_team, int $id): JsonResponse
    {
        abort_unless(
            $this->canManageTrash($request, $current_team),
            403,
            'Only Admins and Owners can restore items from trash.',
        );

        $item = VaultItem::onlyTrashed()
            ->where('team_id', $current_team->id)
            ->findOrFail($id);

        $user = $request->user();
        $item->restore();

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'vault_item_id' => $item->id,
            'action' => 'restored',
            'item_title' => $item->title,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Item restored successfully.',
            'item' => $item->load('user:id,name,email'),
        ]);
    }

    /**
     * Permanently delete item.
     */
    public function forceDelete(Request $request, Team $current_team, int $id): JsonResponse
    {
        abort_unless(
            $this->canManageTrash($request, $current_team),
            403,
            'Only Admins and Owners can permanently delete items.',
        );

        $item = VaultItem::withTrashed()
            ->where('team_id', $current_team->id)
            ->findOrFail($id);

        $item->forceDelete();

        return response()->json([
            'message' => 'Item permanently deleted.',
        ]);
    }

    /**
     * Get version history for a vault item.
     */
    public function history(Request $request, Team $current_team, VaultItem $item): JsonResponse
    {
        abort_if($item->team_id !== $current_team->id, 404);

        $history = $item->histories()
            ->with('user:id,name,email')
            ->latest('id')
            ->get();

        return response()->json([
            'history' => $history,
        ]);
    }

    /**
     * Restore a specific historical version of a vault item.
     */
    public function restoreVersion(Request $request, Team $current_team, VaultItem $item, VaultItemHistory $history): JsonResponse
    {
        abort_if($item->team_id !== $current_team->id, 404);
        abort_if($history->vault_item_id !== $item->id, 404);

        $user = $request->user();

        // Save current into history before reverting to old version
        $item->histories()->create([
            'user_id' => $user->id,
            'encrypted_data' => $item->encrypted_data,
            'iv' => $item->iv,
        ]);

        // Revert data
        $item->update([
            'encrypted_data' => $history->encrypted_data,
            'iv' => $history->iv,
            'password_updated_at' => now(),
        ]);

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'vault_item_id' => $item->id,
            'action' => 'version_restored',
            'item_title' => $item->title,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Vault item restored to selected version.',
            'item' => $item->load('user:id,name,email'),
        ]);
    }

    /**
     * Check if user can manage trash (Admin, Owner, or Personal Vault).
     */
    protected function canManageTrash(Request $request, Team $team): bool
    {
        return $team->is_personal || ($request->user()->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false);
    }

    /**
     * Toggle favorite status.
     */
    public function toggleFavorite(Request $request, Team $current_team, VaultItem $item): JsonResponse
    {
        abort_if($item->team_id !== $current_team->id, 404);

        $item->update([
            'is_favorite' => ! $item->is_favorite,
        ]);

        return response()->json([
            'is_favorite' => $item->is_favorite,
        ]);
    }

    /**
     * Audit an access action (e.g. copied password, revealed, viewed).
     */
    public function audit(Request $request, Team $current_team): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'vault_item_id' => ['nullable', 'exists:vault_items,id'],
            'action' => ['required', 'string', 'max:64'],
            'item_title' => ['nullable', 'string', 'max:255'],
        ]);

        VaultAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $current_team->id,
            'vault_item_id' => $validated['vault_item_id'] ?? null,
            'action' => $validated['action'],
            'item_title' => $validated['item_title'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if (! empty($validated['vault_item_id'])) {
            VaultItem::where('id', $validated['vault_item_id'])->update([
                'last_used_at' => now(),
            ]);
        }

        return response()->json(['status' => 'logged']);
    }
}
