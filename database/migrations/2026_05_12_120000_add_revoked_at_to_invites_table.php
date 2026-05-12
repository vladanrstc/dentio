<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table): void {
            $table->timestamp('revoked_at')->nullable()->after('accepted_at');
            $table->index(['company_id', 'email', 'role', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'email', 'role', 'revoked_at']);
            $table->dropColumn('revoked_at');
        });
    }
};
