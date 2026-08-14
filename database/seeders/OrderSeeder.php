<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();

        if (! $tenant) {
            $this->command->info('No tenant found, skipping order seeding.');
            return;
        }

        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        $customers = [
            ['Acme Industrial', 'procurement@acme.example'],
            ['Global Supplies Ltd', 'orders@globalsupplies.example'],
            ['Northwind Traders', 'ap@northwind.example'],
            ['TechParts Co', 'buyer@techparts.example'],
            ['BuildRight Inc', 'purchasing@buildright.example'],
            ['MediSupply Group', 'ops@medisupply.example'],
            ['Aero Components', 'supply@aerocomponents.example'],
            ['Prime Retail', 'vendor@primeretail.example'],
        ];

        $total = 60;

        for ($i = 0; $i < $total; $i++) {
            $customer = $customers[$i % count($customers)];
            $status = $statuses[$i % count($statuses)];
            $orderNumber = 'ORD-2608-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            Order::firstOrCreate(
                ['order_number' => $orderNumber],
                [
                    'tenant_id' => $tenant->id,
                    'customer_name' => $customer[0],
                    'customer_email' => $customer[1],
                    'status' => $status,
                    'total_amount' => round(250 + ($i * 137.5), 2),
                    'currency' => 'USD',
                    'created_at' => now()->subDays($i % 30)->setTime(8 + ($i % 10), ($i * 7) % 60),
                    'updated_at' => now(),
                ],
            );
        }

        $this->command->info("Seeded orders for tenant {$tenant->id}.");
    }
}
