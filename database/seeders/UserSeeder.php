<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::truncate();

        User::insert([
            [
                'username' => '123456',
                'admin_prev' => true,
            ],
            [
                'username' => '234567',
                'admin_prev' => false,
            ],
        ]);
    }
}
