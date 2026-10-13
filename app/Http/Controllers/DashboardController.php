<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\VaultItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Team $current_team): Response
    {
        $user = $request->user();
        $email = strtolower($user->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        // If a team has other members or invitations, it is automatically a shared team vault
        if ($current_team->is_personal && ($current_team->members()->count() > 1 || $current_team->invitations()->exists())) {
            $current_team->update(['is_personal' => false]);
            $current_team->refresh();
        }

        $canManageTrash = $current_team->is_personal || ($user->teamRole($current_team)?->isAtLeast(TeamRole::Admin) ?? false);

        $baseQuery = VaultItem::query()->where('team_id', $current_team->id);
        if ($current_team->is_personal) {
            $baseQuery->where('user_id', $user->id);
        }

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'logins' => (clone $baseQuery)->where('type', 'login')->count(),
            'cards' => (clone $baseQuery)->where('type', 'card')->count(),
            'notes' => (clone $baseQuery)->where('type', 'note')->count(),
            'servers' => (clone $baseQuery)->where('type', 'server')->count(),
            'favorites' => (clone $baseQuery)->where('is_favorite', true)->count(),
            'trash' => $canManageTrash
                ? ($current_team->is_personal
                    ? VaultItem::onlyTrashed()->where('team_id', $current_team->id)->where('user_id', $user->id)->count()
                    : VaultItem::onlyTrashed()->where('team_id', $current_team->id)->count())
                : 0,
        ];

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'isVaultConfigured' => $user->vaultKey !== null,
            'stats' => $stats,
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
                'is_personal' => (bool) $current_team->is_personal,
                'can_manage_trash' => $canManageTrash,
                'user_role' => $user->teamRole($current_team)?->value ?? ($current_team->is_personal ? 'owner' : 'member'),
            ],
        ]);
    }
}
