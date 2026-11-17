<?php

namespace Database\Seeders;

use App\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketingCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')
            ->where('is_active', true)
            ->first();

        if (!$admin) {
            throw new RuntimeException('No active admin user found for campaign creator.');
        }

        $campaigns = [
            [
                'campaign_code' => 'MKT-2025-001',
                'campaign_name' => 'Holiday Menu Social Media Launch',
                'campaign_type' => 'social_media',
                'platform' => 'Facebook and Instagram',
                'description' => 'Promotional posts featuring Olive Grove Kitchen holiday meals and seasonal menu offerings.',
                'budget' => 12000.00,
                'actual_cost' => 10850.00,
                'start_date' => '2025-11-05',
                'end_date' => '2025-11-30',
                'target_audience' => 'Local families, young professionals, and existing restaurant followers.',
            ],
            [
                'campaign_code' => 'MKT-2025-002',
                'campaign_name' => 'Christmas Family Feast Promotion',
                'campaign_type' => 'social_media',
                'platform' => 'Facebook',
                'description' => 'Campaign promoting group meals, family platters, and advance holiday orders.',
                'budget' => 18000.00,
                'actual_cost' => 16400.00,
                'start_date' => '2025-12-01',
                'end_date' => '2025-12-31',
                'target_audience' => 'Families, office teams, and customers planning Christmas gatherings.',
            ],
            [
                'campaign_code' => 'MKT-2026-001',
                'campaign_name' => 'New Year Customer Email Campaign',
                'campaign_type' => 'email',
                'platform' => 'Email',
                'description' => 'New Year announcements, special menu highlights, and return-customer offers.',
                'budget' => 5000.00,
                'actual_cost' => 3850.00,
                'start_date' => '2026-01-02',
                'end_date' => '2026-01-20',
                'target_audience' => 'Registered customers and previous restaurant customers.',
            ],
            [
                'campaign_code' => 'MKT-2026-002',
                'campaign_name' => 'Valentines Dining SMS Promotion',
                'campaign_type' => 'sms',
                'platform' => 'SMS',
                'description' => 'SMS invitations and promotional announcements for couples dining packages.',
                'budget' => 6500.00,
                'actual_cost' => 5900.00,
                'start_date' => '2026-02-01',
                'end_date' => '2026-02-14',
                'target_audience' => 'Adult customers, couples, and customers with previous dine-in orders.',
            ],
            [
                'campaign_code' => 'MKT-2026-003',
                'campaign_name' => 'Summer Menu Awareness Campaign',
                'campaign_type' => 'social_media',
                'platform' => 'Facebook and Instagram',
                'description' => 'Visual campaign highlighting refreshing beverages, salads, seafood, and summer menu items.',
                'budget' => 10000.00,
                'actual_cost' => 9200.00,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
                'target_audience' => 'Students, young professionals, families, and nearby residents.',
            ],
            [
                'campaign_code' => 'MKT-2026-004',
                'campaign_name' => 'Local Community Flyer Distribution',
                'campaign_type' => 'print',
                'platform' => 'Printed Flyers',
                'description' => 'Distribution of restaurant menus and promotional flyers in nearby residential and commercial areas.',
                'budget' => 7500.00,
                'actual_cost' => 6800.00,
                'start_date' => '2026-04-05',
                'end_date' => '2026-04-25',
                'target_audience' => 'Nearby households, office workers, and local establishments.',
            ],
            [
                'campaign_code' => 'MKT-2026-005',
                'campaign_name' => 'Mothers Day Dining Event',
                'campaign_type' => 'event',
                'platform' => 'In-store Event',
                'description' => 'Special dining event featuring family meal bundles and limited-time offers for Mothers Day.',
                'budget' => 20000.00,
                'actual_cost' => 18750.00,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-10',
                'target_audience' => 'Families, parents, and customers celebrating Mothers Day.',
            ],
            [
                'campaign_code' => 'MKT-2026-006',
                'campaign_name' => 'Midyear Loyalty Customer Campaign',
                'campaign_type' => 'email',
                'platform' => 'Email',
                'description' => 'Customer retention campaign encouraging repeat visits through loyalty announcements and personalized offers.',
                'budget' => 8000.00,
                'actual_cost' => 7200.00,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
                'target_audience' => 'Returning customers and customers with multiple completed orders.',
            ],
            [
                'campaign_code' => 'MKT-2026-007',
                'campaign_name' => 'Back to School Meal Promotion',
                'campaign_type' => 'social_media',
                'platform' => 'Facebook and Instagram',
                'description' => 'Promotion of affordable rice meals, pasta, and quick lunch options during the school season.',
                'budget' => 11000.00,
                'actual_cost' => 10250.00,
                'start_date' => '2026-07-15',
                'end_date' => '2026-08-15',
                'target_audience' => 'Students, parents, teachers, and nearby office employees.',
            ],
            [
                'campaign_code' => 'MKT-2026-008',
                'campaign_name' => 'Customer Appreciation Month',
                'campaign_type' => 'other',
                'platform' => 'In-store and Online',
                'description' => 'Customer appreciation activities, feedback collection, and promotional offers for loyal customers.',
                'budget' => 15000.00,
                'actual_cost' => 13900.00,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'target_audience' => 'All customers, especially returning and highly engaged customers.',
            ],
        ];

        DB::transaction(function () use ($campaigns, $admin) {
            foreach ($campaigns as $campaign) {
                $campaign['status'] = 'completed';
                $campaign['created_by'] = $admin->id;

                MarketingCampaign::updateOrCreate(
                    ['campaign_code' => $campaign['campaign_code']],
                    $campaign
                );
            }
        });

        $this->command->info(count($campaigns) . ' marketing campaigns created or updated successfully.');
    }
}