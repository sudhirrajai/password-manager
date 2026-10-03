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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('vault_salt', 128)->nullable()->after('is_personal');
            $table->text('encrypted_vault_key')->nullable()->after('vault_salt');
            $table->string('vault_key_iv', 64)->nullable()->after('encrypted_vault_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['vault_salt', 'encrypted_vault_key', 'vault_key_iv']);
        });
    }
};
