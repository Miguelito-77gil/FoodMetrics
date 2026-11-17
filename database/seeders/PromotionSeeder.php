<?php

namespace Database\Seeders;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')
            ->where('is_active', true)
            ->first();

        if (!$admin) {
            throw new RuntimeException('No active admin user found for promotion creator.');
        }

        $promotions = [
            [
                'promotion_code' => 'WELCOME10',
                'promotion_name' => 'Welcome Customer Offer',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'minimum_order_amount' => 300.00,
                'maximum_discount_amount' => 100.00,
                'start_date' => '2025-11-01',
                'end_date' => '2025-11-30',
                'usage_limit' => 100,
                'description' => 'Simulated welcome discount for new Olive Grove Kitchen customers.',
            ],
            [
                'promotion_code' => 'FAMILY150',
                'promotion_name' => 'Family Feast Discount',
                'discount_type' => 'fixed_amount',
                'discount_value' => 150.00,
                'minimum_order_amount' => 1000.00,
                'maximum_discount_amount' => null,
                'start_date' => '2025-12-01',
                'end_date' => '2025-12-31',
                'usage_limit' => 80,
                'description' => 'Simulated fixed discount for qualifying family meal orders.',
            ],
            [
                'promotion_code' => 'HOLIDAY15',
                'promotion_name' => 'Holiday Treat Promotion',
                'discount_type' => 'percentage',
                'discount_value' => 15.00,
                'minimum_order_amount' => 800.00,
                'maximum_discount_amount' => 200.00,
                'start_date' => '2025-12-15',
                'end_date' => '2025-12-31',
                'usage_limit' => 120,
                'description' => 'Simulated holiday season discount on qualifying restaurant orders.',
            ],
            [
                'promotion_code' => 'NEWYEAR100',
                'promotion_name' => 'New Year Special',
                'discount_type' => 'fixed_amount',
                'discount_value' => 100.00,
                'minimum_order_amount' => 500.00,
                'maximum_discount_amount' => null,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-20',
                'usage_limit' => 90,
                'description' => 'Simulated New Year savings for dine-in and takeout customers.',
            ],
            [
                'promotion_code' => 'LOVE12',
                'promotion_name' => 'Valentines Dining Deal',
                'discount_type' => 'percentage',
                'discount_value' => 12.00,
                'minimum_order_amount' => 600.00,
                'maximum_discount_amount' => 150.00,
                'start_date' => '2026-02-01',
                'end_date' => '2026-02-14',
                'usage_limit' => 75,
                'description' => 'Simulated Valentines discount for couples and group dining orders.',
            ],
            [
                'promotion_code' => 'SUMMER10',
                'promotion_name' => 'Summer Saver',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'minimum_order_amount' => 400.00,
                'maximum_discount_amount' => 100.00,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
                'usage_limit' => 150,
                'description' => 'Simulated summer promotion for selected meals and refreshing beverages.',
            ],
            [
                'promotion_code' => 'MOM200',
                'promotion_name' => 'Mothers Day Family Feast',
                'discount_type' => 'fixed_amount',
                'discount_value' => 200.00,
                'minimum_order_amount' => 1200.00,
                'maximum_discount_amount' => null,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-10',
                'usage_limit' => 60,
                'description' => 'Simulated Mothers Day discount for qualifying family orders.',
            ],
            [
                'promotion_code' => 'MIDYEAR8',
                'promotion_name' => 'Midyear Dining Special',
                'discount_type' => 'percentage',
                'discount_value' => 8.00,
                'minimum_order_amount' => 500.00,
                'maximum_discount_amount' => 100.00,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
                'usage_limit' => 100,
                'description' => 'Simulated midyear promotion encouraging repeat customer visits.',
            ],
            [
                'promotion_code' => 'SCHOOL50',
                'promotion_name' => 'Back to School Savings',
                'discount_type' => 'fixed_amount',
                'discount_value' => 50.00,
                'minimum_order_amount' => 250.00,
                'maximum_discount_amount' => null,
                'start_date' => '2026-07-15',
                'end_date' => '2026-08-15',
                'usage_limit' => 180,
                'description' => 'Simulated affordable meal promotion for students and school communities.',
            ],
            [
                'promotion_code' => 'THANKYOU20',
                'promotion_name' => 'Customer Appreciation Discount',
                'discount_type' => 'percentage',
                'discount_value' => 20.00,
                'minimum_order_amount' => 1000.00,
                'maximum_discount_amount' => 250.00,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'usage_limit' => 100,
                'description' => 'Simulated customer appreciation offer for loyal Olive Grove Kitchen customers.',
            ],
        ];

        DB::transaction(function () use ($promotions, $admin) {
            foreach ($promotions as $promotion) {
                $promotion['status'] = 'expired';
                $promotion['usage_count'] = 0;
                $promotion['created_by'] = $admin->id;

                Promotion::updateOrCreate(
                    ['promotion_code' => $promotion['promotion_code']],
                    $promotion
                );
            }
        });

        $this->command->info(count($promotions) . ' promotions created or updated successfully.');
    }
}