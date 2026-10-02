<?php

namespace App\Http\Controllers\Vault;

use App\Http\Controllers\Controller;
use App\Models\SecretLink;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SecretLinkController extends Controller
{
    /**
     * List user's active secret links.
     */
    public function index(Request $request, Team $current_team): JsonResponse
    {
        $links = SecretLink::query()
            ->where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())
            ->where(function ($query) {
                $query->where('max_views', 0)
                    ->orWhereColumn('view_count', '<', 'max_views');
            })
            ->latest()
            ->get();

        return response()->json([
            'links' => $links,
        ]);
    }

    /**
     * Store a new one-time encrypted secret link.
     */
    public function store(Request $request, Team $current_team): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'encrypted_data' => ['required', 'string'],
            'iv' => ['required', 'string', 'max:255'],
            'expires_in_hours' => ['required', 'integer', 'min:1', 'max:720'], // up to 30 days
            'max_views' => ['required', 'integer', 'min:1', 'max:100'],
            'passphrase' => ['nullable', 'string', 'min:4'],
        ]);

        $token = Str::random(32);

        $secret = SecretLink::create([
            'user_id' => $request->user()->id,
            'token' => $token,
            'title' => $validated['title'] ?? 'Secret Note / Credential',
            'encrypted_data' => $validated['encrypted_data'],
            'iv' => $validated['iv'],
            'max_views' => $validated['max_views'],
            'view_count' => 0,
            'expires_at' => now()->addHours($validated['expires_in_hours']),
            'passphrase_hash' => ! empty($validated['passphrase']) ? Hash::make($validated['passphrase']) : null,
        ]);

        return response()->json([
            'message' => 'Secret link created successfully.',
            'secret' => $secret,
            'token' => $token,
        ], 201);
    }

    /**
     * Delete/Revoke a secret link.
     */
    public function destroy(Request $request, Team $current_team, SecretLink $secretLink): JsonResponse
    {
        abort_if($secretLink->user_id !== $request->user()->id, 403);

        $secretLink->delete();

        return response()->json([
            'message' => 'Secret link revoked.',
        ]);
    }

    /**
     * Public access endpoint for recipient to fetch secret.
     */
    public function publicShow(Request $request, string $token): JsonResponse
    {
        $secret = SecretLink::query()
            ->where('token', $token)
            ->first();

        if (! $secret || $secret->isExpired()) {
            return response()->json([
                'error' => 'This secret has expired or has already been viewed and destroyed.',
            ], 404);
        }

        // If secret is protected by a secondary passphrase
        if ($secret->passphrase_hash) {
            $passphrase = $request->input('passphrase');
            if (empty($passphrase) || ! Hash::check($passphrase, $secret->passphrase_hash)) {
                return response()->json([
                    'requires_passphrase' => true,
                    'title' => $secret->title,
                    'error' => empty($passphrase) ? null : 'Invalid passphrase.',
                ], 401);
            }
        }

        $secret->increment('view_count');

        $isNowDestroyed = $secret->max_views > 0 && $secret->view_count >= $secret->max_views;

        return response()->json([
            'title' => $secret->title,
            'encrypted_data' => $secret->encrypted_data,
            'iv' => $secret->iv,
            'expires_at' => $secret->expires_at,
            'max_views' => $secret->max_views,
            'view_count' => $secret->view_count,
            'is_destroyed' => $isNowDestroyed,
        ]);
    }
}
