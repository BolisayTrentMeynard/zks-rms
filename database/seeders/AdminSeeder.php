<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates (or updates) the one shared resort Admin account.
 * Email and password come from .env (ADMIN_EMAIL, ADMIN_PASSWORD); they are never stored in code.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('zk.admin.email');
        $password = config('zk.admin.password');

        if (! $email || ! $password) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in your .env file, then run the seeder again.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('zk.admin.name'),
                'password' => $password,
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );
    }
}
