<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'invitation' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'email_verified_at' => now(), // Auto-verify on registration for smooth developer & personal usage
            ]);

            // Create default personal team / personal vault
            $team = (new CreateTeam)->handle($user, $user->name."'s Vault", true);

            // If registering via team invitation, automatically attach to that team
            if (! empty($input['invitation'])) {
                $invitation = TeamInvitation::query()
                    ->where('code', $input['invitation'])
                    ->whereNull('accepted_at')
                    ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                    ->first();

                if ($invitation) {
                    $invitation->team->memberships()->create([
                        'user_id' => $user->id,
                        'role' => $invitation->role,
                    ]);

                    $invitation->update(['accepted_at' => now()]);
                    $user->switchTeam($invitation->team);
                }
            }

            return $user;
        });
    }
}
