<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('FoodMetrics@2026');

        $users = [
            [
                'name' => 'Olive Grove Owner',
                'email' => 'owner@olivegrovekitchen.test',
                'role' => 'admin',
                'phone' => '09170000001',
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'cashier1@olivegrovekitchen.test',
                'role' => 'cashier',
                'phone' => '09170000002',
            ],
            [
                'name' => 'Juan Reyes',
                'email' => 'cashier2@olivegrovekitchen.test',
                'role' => 'cashier',
                'phone' => '09170000003',
            ],
            [
                'name' => 'Ana Cruz',
                'email' => 'cashier3@olivegrovekitchen.test',
                'role' => 'cashier',
                'phone' => '09170000004',
            ],
            [
                'name' => 'Pedro Garcia',
                'email' => 'inventory1@olivegrovekitchen.test',
                'role' => 'inventory_staff',
                'phone' => '09170000005',
            ],
            [
                'name' => 'Sofia Mendoza',
                'email' => 'inventory2@olivegrovekitchen.test',
                'role' => 'inventory_staff',
                'phone' => '09170000006',
            ],
            [
                'name' => 'Carlos Dela Cruz',
                'email' => 'delivery1@olivegrovekitchen.test',
                'role' => 'delivery_staff',
                'phone' => '09170000007',
            ],
            [
                'name' => 'Miguel Ramos',
                'email' => 'delivery2@olivegrovekitchen.test',
                'role' => 'delivery_staff',
                'phone' => '09170000008',
            ],
            [
                'name' => 'Isabella Torres',
                'email' => 'delivery3@olivegrovekitchen.test',
                'role' => 'delivery_staff',
                'phone' => '09170000009',
            ],
        ];

        DB::transaction(function () use ($users, $password) {
            foreach ($users as $userData) {
                User::updateOrCreate(
                    ['email' => $userData['email']],
                    [
                        'name' => $userData['name'],
                        'password' => $password,
                        'role' => $userData['role'],
                        'phone' => $userData['phone'],
                        'is_active' => true,
                    ]
                );
            }
        });

        $this->command?->info('FoodMetrics demo users seeded successfully.');
    }
}