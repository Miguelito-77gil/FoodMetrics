<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $firstNames = [
            'Maria', 'Juan', 'Ana', 'Jose', 'Sofia',
            'Carlos', 'Isabella', 'Miguel', 'Angela', 'Gabriel',
        ];

        $lastNames = [
            'Santos', 'Reyes', 'Cruz', 'Garcia', 'Mendoza',
            'Ramos', 'Dela Cruz', 'Torres', 'Villanueva', 'Bautista',
        ];

        $addresses = [
            'Cebu City, Cebu',
            'Mandaue City, Cebu',
            'Lapu-Lapu City, Cebu',
            'Talisay City, Cebu',
            'Consolacion, Cebu',
            'Danao City, Cebu',
            'Carcar City, Cebu',
            'Minglanilla, Cebu',
            'Cordova, Cebu',
            'Naga City, Cebu',
        ];

        $genders = [
            'female',
            'male',
            'female',
            'male',
            'female',
            'male',
            'female',
            'male',
            'other',
            'prefer_not_to_say',
        ];

        for ($i = 0; $i < 50; $i++) {
            $firstName = $firstNames[intdiv($i, 5)];
            $lastName = $lastNames[($i * 3) % 10];

            $number = $i + 1;
            $customerCode = 'CUS-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);

            $emailName = strtolower($firstName . '.' . str_replace(' ', '', $lastName));

            $birthYear = 1972 + (($i * 7) % 34);
            $birthMonth = ($i % 12) + 1;
            $birthDay = (($i * 3) % 27) + 1;

            $birthDate = sprintf(
                '%04d-%02d-%02d',
                $birthYear,
                $birthMonth,
                $birthDay
            );

            Customer::updateOrCreate(
                ['customer_code' => $customerCode],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $emailName . $number . '@example.test',
                    'phone' => '0917' . str_pad(
                        (string) (1000000 + $number),
                        7,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'address' => $addresses[$i % count($addresses)],
                    'birth_date' => $birthDate,
                    'gender' => $genders[$i % count($genders)],
                    'is_active' => ($number % 10 !== 0),
                    'notes' => null,
                ]
            );
        }

        $this->command?->info('50 simulated FoodMetrics customers seeded successfully.');
    }
}