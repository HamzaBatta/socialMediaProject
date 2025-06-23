<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 5 users with specific data
        $users = collect();

        for ($i = 1; $i <= 5; $i++) {
            $users->push(User::create([
                'name' => "user$i",
                'username' => "User$i",
                'email' => "user$i@gmail.com",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_private' => false,
            ]));
        }

        // Define randomized follow relations manually for realism
        $follows = [
            1 => [2, 3],       // user 1 follows 2, 3
            2 => [4, 5],       // user 2 follows 4, 5
            3 => [2],          // user 3 follows 2
            4 => [1, 5],       // user 4 follows 1, 5
            5 => [1, 3],       // user 5 follows 1, 3
        ];

        // Attach follow relationships
        foreach ($follows as $follower => $followees) {
            $followerUser = $users[$follower - 1]; // adjust for 0-based index
            foreach ($followees as $followee) {
                $followerUser->following()->attach($users[$followee - 1]->id);
            }
        }
    }
}
