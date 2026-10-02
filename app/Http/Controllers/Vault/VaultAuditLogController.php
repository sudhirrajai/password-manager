<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\VaultAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultAuditLogController extends Controller
{
    /**
     * Get recent audit logs for the current team.
     */
    public function index(Request $request, Team $current_team): JsonResponse
    {
        $logs = VaultAuditLog::query()
            ->where('team_id', $current_team->id)
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'logs' => $logs,
        ]);
    }
}
