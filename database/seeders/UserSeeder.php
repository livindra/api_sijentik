<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // create an admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'address' => 'Jl. Admin No.1',
            'rtrw' => '01/01',
            'password' => Hash::make('password'),
            'role' => 'Admin',
            'status' => 'approved',
        ]);

        // create 5 random users
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->make();
            $user->address = fake()->address();
            $user->rtrw = fake()->bothify('##/##');
            $user->role = 'Kader';
            $user->status = 'approved';
            $user->profile_photo = null;
            $user->otp = null;
            $user->otp_expires_at = null;
            $user->save();
        }
    }
}
