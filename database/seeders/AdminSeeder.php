<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Operator accounts (separate `admin` guard). */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Admin::updateOrCreate(
            ['email' => 'admin@poisapay.test'],
            [
                'name' => 'PoisaPay Operator',
                'username' => 'operator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
        $superAdmin->syncRoles(['super-admin']);

        $compliance = Admin::updateOrCreate(
            ['email' => 'compliance@poisapay.test'],
            [
                'name' => 'Compliance Officer',
                'username' => 'compliance',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
        $compliance->syncRoles(['compliance']);
    }
}
