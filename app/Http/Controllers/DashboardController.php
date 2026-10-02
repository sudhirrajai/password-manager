<?php

namespace App\Http\Controllers;

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

        $stats = [
            'total' => VaultItem::where('team_id', $current_team->id)->count(),
            'logins' => VaultItem::where('team_id', $current_team->id)->where('type', 'login')->count(),
            'cards' => VaultItem::where('team_id', $current_team->id)->where('type', 'card')->count(),
            'notes' => VaultItem::where('team_id', $current_team->id)->where('type', 'note')->count(),
            'servers' => VaultItem::where('team_id', $current_team->id)->where('type', 'server')->count(),
            'favorites' => VaultItem::where('team_id', $current_team->id)->where('is_favorite', true)->count(),
            'trash' => VaultItem::onlyTrashed()->where('team_id', $current_team->id)->count(),
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
            ],
        ]);
    }
}
