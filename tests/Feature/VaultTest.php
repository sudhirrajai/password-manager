<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Models\VaultItem;

test('new user registration automatically creates personal team and logs in', function () {
    $response = $this->post(route('register'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ]);

    $user = User::where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->currentTeam)->not->toBeNull();
    expect($user->currentTeam->is_personal)->toBeTrue();

    $response->assertRedirect();
});

test('user can save and retrieve vault encryption keys', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $storeResponse = $this
        ->actingAs($user)
        ->postJson(route('vault.key.store', $team->slug), [
            'vault_salt' => 'random_salt_base64_string',
            'encrypted_vault_key' => 'encrypted_uvk_blob',
            'vault_key_iv' => 'random_iv_base64',
        ]);

    $storeResponse->assertOk()
        ->assertJson(['is_configured' => true]);

    $showResponse = $this
        ->actingAs($user)
        ->getJson(route('vault.key.show', $team->slug));

    $showResponse->assertOk()
        ->assertJson([
            'is_configured' => true,
            'vault_salt' => 'random_salt_base64_string',
            'encrypted_vault_key' => 'encrypted_uvk_blob',
            'vault_key_iv' => 'random_iv_base64',
        ]);
});

test('user can recover vault keys using recovery key payload', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    // 1. Initial vault setup with recovery key
    $this->actingAs($user)->postJson(route('vault.key.store', $team->slug), [
        'vault_salt' => 'salt_v1',
        'encrypted_vault_key' => 'encrypted_uvk_v1',
        'vault_key_iv' => 'iv_v1',
        'recovery_salt' => 'rec_salt_v1',
        'encrypted_recovery_key' => 'encrypted_uvk_by_rec_v1',
        'recovery_key_iv' => 'rec_iv_v1',
    ])->assertOk();

    // 2. Recover with new master password payload
    $recoverResponse = $this->actingAs($user)->postJson(route('vault.key.recover', $team->slug), [
        'new_vault_salt' => 'salt_v2',
        'new_encrypted_vault_key' => 'encrypted_uvk_v2',
        'new_vault_key_iv' => 'iv_v2',
        'new_recovery_salt' => 'rec_salt_v2',
        'new_encrypted_recovery_key' => 'encrypted_uvk_by_rec_v2',
        'new_recovery_key_iv' => 'rec_iv_v2',
    ]);

    $recoverResponse->assertOk()->assertJson(['is_configured' => true]);

    $user->refresh();
    $vaultKey = $user->vaultKey;
    expect($vaultKey->vault_salt)->toBe('salt_v2');
    expect($vaultKey->encrypted_vault_key)->toBe('encrypted_uvk_v2');
    expect($vaultKey->encrypted_recovery_key)->toBe('encrypted_uvk_by_rec_v2');
});

test('user can wipe and reset vault when keys are lost', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)->postJson(route('vault.key.store', $team->slug), [
        'vault_salt' => 'salt_v1',
        'encrypted_vault_key' => 'encrypted_uvk_v1',
        'vault_key_iv' => 'iv_v1',
    ])->assertOk();

    $this->actingAs($user)->postJson(route('vault.items.store', $team->slug), [
        'type' => 'login',
        'title' => 'Personal Account',
        'encrypted_data' => 'ciphertext',
        'iv' => 'iv',
    ])->assertCreated();

    expect(VaultItem::where('user_id', $user->id)->count())->toBe(1);

    // Reset vault
    $resetResponse = $this->actingAs($user)->postJson(route('vault.key.reset', $team->slug));
    $resetResponse->assertOk()->assertJson(['is_configured' => false]);

    expect($user->fresh()->vaultKey)->toBeNull();
    expect(VaultItem::where('user_id', $user->id)->count())->toBe(0);
});

test('user can create, update, favorite, soft-delete and restore vault items', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    // 1. Create
    $createResponse = $this
        ->actingAs($user)
        ->postJson(route('vault.items.store', $team->slug), [
            'type' => 'login',
            'title' => 'GitHub Account',
            'encrypted_data' => 'ciphertext_payload_base64',
            'iv' => 'iv_base64',
            'is_favorite' => false,
            'folder' => 'Dev',
        ]);

    $createResponse->assertCreated();
    $item = VaultItem::where('title', 'GitHub Account')->first();
    expect($item)->not->toBeNull();
    expect($item->team_id)->toBe($team->id);

    // 2. Toggle Favorite
    $favResponse = $this
        ->actingAs($user)
        ->postJson(route('vault.items.favorite', [$team->slug, $item->id]));

    $favResponse->assertOk()->assertJson(['is_favorite' => true]);
    expect($item->fresh()->is_favorite)->toBeTrue();

    // 3. Update
    $updateResponse = $this
        ->actingAs($user)
        ->putJson(route('vault.items.update', [$team->slug, $item->id]), [
            'title' => 'GitHub Enterprise',
            'encrypted_data' => 'new_ciphertext',
            'iv' => 'new_iv',
        ]);

    $updateResponse->assertOk();
    expect($item->fresh()->title)->toBe('GitHub Enterprise');

    // 4. Soft-delete (Move to Trash)
    $deleteResponse = $this
        ->actingAs($user)
        ->deleteJson(route('vault.items.destroy', [$team->slug, $item->id]));

    $deleteResponse->assertOk();
    expect(VaultItem::where('id', $item->id)->count())->toBe(0);
    expect(VaultItem::onlyTrashed()->where('id', $item->id)->count())->toBe(1);

    // 5. Restore from Trash
    $restoreResponse = $this
        ->actingAs($user)
        ->postJson(route('vault.items.restore', [$team->slug, $item->id]));

    $restoreResponse->assertOk();
    expect(VaultItem::where('id', $item->id)->count())->toBe(1);
});

test('one-time secret link self-destructs after view limit is reached', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $createResponse = $this
        ->actingAs($user)
        ->postJson(route('vault.shares.store', $team->slug), [
            'title' => 'Server Passphrase',
            'encrypted_data' => 'encrypted_secret_data',
            'iv' => 'share_iv',
            'expires_in_hours' => 24,
            'max_views' => 1,
        ]);

    $createResponse->assertCreated();
    $token = $createResponse->json('token');
    expect($token)->not->toBeNull();

    // View 1 (First view should succeed and mark destroyed)
    $viewResponse = $this->getJson(route('api.secret.show', $token));
    $viewResponse->assertOk()
        ->assertJson([
            'title' => 'Server Passphrase',
            'encrypted_data' => 'encrypted_secret_data',
            'is_destroyed' => true,
        ]);

    // View 2 (Second view should return 404 expired/destroyed)
    $secondView = $this->getJson(route('api.secret.show', $token));
    $secondView->assertNotFound();
});

test('vault access events are recorded in audit logs', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $auditResponse = $this
        ->actingAs($user)
        ->postJson(route('vault.items.audit', $team->slug), [
            'action' => 'copied_password',
            'item_title' => 'AWS Console',
        ]);

    $auditResponse->assertOk();

    $listResponse = $this
        ->actingAs($user)
        ->getJson(route('vault.audit.index', $team->slug));

    $listResponse->assertOk()
        ->assertJsonFragment([
            'action' => 'copied_password',
            'item_title' => 'AWS Console',
        ]);
});

test('user can rotate recovery key payload', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)->postJson(route('vault.key.store', $team->slug), [
        'vault_salt' => 'salt_1',
        'encrypted_vault_key' => 'uvk_1',
        'vault_key_iv' => 'iv_1',
    ])->assertOk();

    $response = $this->actingAs($user)->postJson(route('vault.key.rotate-recovery', $team->slug), [
        'recovery_salt' => 'rotated_salt',
        'encrypted_recovery_key' => 'rotated_encrypted_key',
        'recovery_key_iv' => 'rotated_iv',
    ]);

    $response->assertOk()->assertJson(['has_recovery_key' => true]);

    $vaultKey = $user->fresh()->vaultKey;
    expect($vaultKey->recovery_salt)->toBe('rotated_salt');
    expect($vaultKey->encrypted_recovery_key)->toBe('rotated_encrypted_key');
});

test('team member can delete item to trash but cannot access or restore from trash', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create(['is_personal' => false]);
    $team->memberships()->create(['user_id' => $owner->id, 'role' => TeamRole::Owner]);
    $owner->switchTeam($team);

    $member = User::factory()->create();
    $team->memberships()->create(['user_id' => $member->id, 'role' => TeamRole::Member]);
    $member->switchTeam($team);

    // Create item
    $item = VaultItem::create([
        'team_id' => $team->id,
        'user_id' => $owner->id,
        'type' => 'login',
        'title' => 'GitHub Org Secret',
        'encrypted_data' => 'ciphertext_secret',
        'iv' => 'iv_123',
    ]);

    // Member can move to trash (soft delete)
    $this->actingAs($member)
        ->deleteJson(route('vault.items.destroy', ['current_team' => $team->slug, 'item' => $item->id]))
        ->assertOk();

    expect($item->fresh()->trashed())->toBeTrue();

    // Member CANNOT access trash (forbidden 403)
    $this->actingAs($member)
        ->getJson(route('vault.items.index', ['current_team' => $team->slug, 'trash' => 1]))
        ->assertForbidden();

    // Member CANNOT restore from trash (forbidden 403)
    $this->actingAs($member)
        ->postJson(route('vault.items.restore', ['current_team' => $team->slug, 'id' => $item->id]))
        ->assertForbidden();

    // Member CANNOT permanently delete from trash (forbidden 403)
    $this->actingAs($member)
        ->deleteJson(route('vault.items.force', ['current_team' => $team->slug, 'id' => $item->id]))
        ->assertForbidden();

    // Owner CAN access trash
    $this->actingAs($owner)
        ->getJson(route('vault.items.index', ['current_team' => $team->slug, 'trash' => 1]))
        ->assertOk()
        ->assertJsonFragment(['title' => 'GitHub Org Secret']);

    // Owner CAN restore item
    $this->actingAs($owner)
        ->postJson(route('vault.items.restore', ['current_team' => $team->slug, 'id' => $item->id]))
        ->assertOk();

    expect($item->fresh()->trashed())->toBeFalse();
});

test('updating item encrypted payload creates version history record and allows restoration', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $item = VaultItem::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'type' => 'server',
        'title' => 'Production Bastion',
        'encrypted_data' => 'v1_ciphertext',
        'iv' => 'v1_iv',
    ]);

    // Update item credentials
    $updateResponse = $this->actingAs($user)
        ->putJson(route('vault.items.update', ['current_team' => $team->slug, 'item' => $item->id]), [
            'encrypted_data' => 'v2_ciphertext_new_password_and_pem',
            'iv' => 'v2_iv',
        ]);

    $updateResponse->assertOk();

    // History record should exist
    $histories = $item->histories()->get();
    expect($histories)->toHaveCount(1);
    expect($histories->first()->encrypted_data)->toBe('v1_ciphertext');
    expect($histories->first()->iv)->toBe('v1_iv');

    // Fetch history endpoint
    $historyResponse = $this->actingAs($user)
        ->getJson(route('vault.items.history', ['current_team' => $team->slug, 'item' => $item->id]));

    $historyResponse->assertOk()
        ->assertJsonCount(1, 'history')
        ->assertJsonFragment(['encrypted_data' => 'v1_ciphertext']);

    // Restore historical version
    $historyRecord = $histories->first();
    $restoreResponse = $this->actingAs($user)
        ->postJson(route('vault.items.history.restore', [
            'current_team' => $team->slug,
            'item' => $item->id,
            'history' => $historyRecord->id,
        ]));

    $restoreResponse->assertOk();

    // Item should now have v1 data restored
    $freshItem = $item->fresh();
    expect($freshItem->encrypted_data)->toBe('v1_ciphertext');
    expect($freshItem->iv)->toBe('v1_iv');

    // And a new history record should archive v2 data
    expect($item->histories()->count())->toBe(2);
});

