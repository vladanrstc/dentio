<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $email = mb_strtolower((string) env('SUPER_ADMIN_EMAIL', 'superadmin@dentio.local'));
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'SuperAdmin123!');
        $firstName = trim((string) env('SUPER_ADMIN_FIRST_NAME', 'Super'));
        $lastName = trim((string) env('SUPER_ADMIN_LAST_NAME', 'Admin'));
        $name = trim($firstName.' '.$lastName);
        $now = Carbon::now();

        $payload = [
            'company_id' => null,
            'name' => $name !== '' ? $name : 'Super Admin',
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'phone' => null,
            'role' => User::ROLE_PLATFORM_ADMIN,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => $now,
            'updated_at' => $now,
        ];

        $existing = DB::table('users')->where('email', $email)->first();

        if ($existing) {
            DB::table('users')
                ->where('id', $existing->id)
                ->update($payload);

            return;
        }

        DB::table('users')->insert(array_merge($payload, [
            'remember_token' => null,
            'created_at' => $now,
        ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $email = mb_strtolower((string) env('SUPER_ADMIN_EMAIL', 'superadmin@dentio.local'));

        DB::table('users')
            ->where('email', $email)
            ->where('role', User::ROLE_PLATFORM_ADMIN)
            ->delete();
    }
};

