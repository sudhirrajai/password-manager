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
        Schema::table('vault_keys', function (Blueprint $table) {
            $table->string('recovery_salt', 128)->nullable()->after('vault_key_iv');
            $table->text('encrypted_recovery_key')->nullable()->after('recovery_salt');
            $table->string('recovery_key_iv', 64)->nullable()->after('encrypted_recovery_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vault_keys', function (Blueprint $table) {
            $table->dropColumn([
                'recovery_salt',
                'encrypted_recovery_key',
                'recovery_key_iv',
            ]);
        });
    }
};
