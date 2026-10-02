<?php

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
