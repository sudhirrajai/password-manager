<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vault_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('vault_salt', 128);
            $table->text('encrypted_vault_key');
            $table->string('vault_key_iv', 64);
            $table->timestamps();
        });

        Schema::create('team_vault_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('encrypted_team_key');
            $table->string('team_key_iv', 64);
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('vault_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->default('login'); // login, card, note, server
            $table->text('title');
            $table->longText('encrypted_data');
            $table->string('iv', 64);
            $table->boolean('is_favorite')->default(false);
            $table->string('folder')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('password_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'deleted_at']);
            $table->index(['team_id', 'type']);
            $table->index(['user_id', 'deleted_at']);
        });

        Schema::create('secret_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('title')->nullable();
            $table->longText('encrypted_data');
            $table->string('iv', 64);
            $table->unsignedInteger('max_views')->default(1);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('expires_at');
            $table->string('passphrase_hash')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('vault_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vault_item_id')->nullable()->constrained('vault_items')->nullOnDelete();
            $table->string('action', 64); // created, viewed, copied_password, copied_username, copied_totp, updated, deleted, restored, exported
            $table->string('item_title')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_audit_logs');
        Schema::dropIfExists('secret_links');
        Schema::dropIfExists('vault_items');
        Schema::dropIfExists('team_vault_keys');
        Schema::dropIfExists('vault_keys');
    }
};
