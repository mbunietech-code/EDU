<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@mbunietech.co.tz'],
            [
                'name' => 'MBUNIETECH Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'status' => 'active',
            ]
        );

        $demoUser = User::firstOrCreate(
            ['email' => 'demo@mbunietech.co.tz'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => false,
                'status' => 'active',
            ]
        );

        $products = [
            [
                'name' => 'ChatGPT Plus',
                'slug' => 'chatgpt-plus',
                'description' => 'Premium conversational AI with advanced reasoning, image generation and voice. Managed access on a shared authorized account.',
                'features' => [
                    'Advanced GPT-4 class model access',
                    'Image generation capability',
                    'Voice conversations',
                    'Higher rate limits',
                    'Dedicated support channel',
                ],
                'price' => 45000,
                'status' => 'published',
                'is_featured' => true,
                'plans' => [
                    ['name' => 'Monthly', 'duration_days' => 30, 'price' => 45000, 'sort_order' => 1],
                    ['name' => 'Quarterly', 'duration_days' => 90, 'price' => 120000, 'sort_order' => 2],
                ],
                'accounts' => 6,
            ],
            [
                'name' => 'Claude Pro',
                'slug' => 'claude-pro',
                'description' => 'Anthropic Claude with long-context conversations, analysis and coding assistance. Managed subscription with daily limits reset.',
                'features' => [
                    'Claude long-context model',
                    'Document analysis',
                    'Coding assistant',
                    'Daily usage quota',
                    'Priority support',
                ],
                'price' => 50000,
                'status' => 'published',
                'is_featured' => true,
                'plans' => [
                    ['name' => 'Monthly', 'duration_days' => 30, 'price' => 50000, 'sort_order' => 1],
                    ['name' => 'Quarterly', 'duration_days' => 90, 'price' => 135000, 'sort_order' => 2],
                ],
                'accounts' => 5,
            ],
            [
                'name' => 'Midjourney',
                'slug' => 'midjourney',
                'description' => 'Professional AI image generation for creators and designers. Fast lane queues on a shared managed account.',
                'features' => [
                    'High-quality image generation',
                    'Fast generation queue',
                    'Variation and upscale tools',
                    'Commercial use',
                    'Community guidance',
                ],
                'price' => 40000,
                'status' => 'published',
                'is_featured' => false,
                'plans' => [
                    ['name' => 'Monthly', 'duration_days' => 30, 'price' => 40000, 'sort_order' => 1],
                ],
                'accounts' => 4,
            ],
            [
                'name' => 'Bing Copilot Pro',
                'slug' => 'bing-copilot-pro',
                'description' => 'Copilot Pro with priority access, faster responses and deeper creation tools. Shared authorized access.',
                'features' => [
                    'Priority model access',
                    'Faster responses',
                    'Creation tools',
                    'AI image generation',
                ],
                'price' => 30000,
                'status' => 'published',
                'is_featured' => false,
                'plans' => [
                    ['name' => 'Monthly', 'duration_days' => 30, 'price' => 30000, 'sort_order' => 1],
                ],
                'accounts' => 3,
            ],
        ];

        foreach ($products as $productData) {
            $accountsCount = $productData['accounts'];
            unset($productData['accounts']);
            $plansData = $productData['plans'];
            unset($productData['plans']);

            $product = Product::firstOrCreate(
                ['slug' => $productData['slug']],
                $productData
            );

            if ($product->wasRecentlyCreated) {
                foreach ($plansData as $planData) {
                    Plan::firstOrCreate(
                        ['product_id' => $product->id, 'name' => $planData['name']],
                        $planData
                    );
                }

                for ($i = 1; $i <= $accountsCount; $i++) {
                    Account::create([
                        'product_id' => $product->id,
                        'name' => $product->name . ' Account ' . $i,
                        'description' => 'Shared authorized access account managed by MBUNIETECH.',
                        'credentials' => null,
                        'status' => 'available',
                        'metadata' => [
                            'type' => 'shared',
                            'seats' => $i === 1 ? 1 : null,
                        ],
                    ]);
                }
            }
        }

        $settings = [
            ['key' => 'support_email', 'value' => 'support@mbunietech.co.tz', 'type' => 'string', 'group' => 'general', 'description' => 'Public support email address'],
            ['key' => 'expiry_warning_days', 'value' => '3', 'type' => 'integer', 'group' => 'subscriptions', 'description' => 'Days before expiry to warn the user'],
            ['key' => 'payment_methods', 'value' => 'bank_transfer,mobile_money', 'type' => 'string', 'group' => 'payments', 'description' => 'Comma separated accepted payment methods'],
            ['key' => 'currency', 'value' => 'TZS', 'type' => 'string', 'group' => 'general', 'description' => 'Default currency display'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}