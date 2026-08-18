<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        $products = Product::query()->where('is_active', true)->get(['id', 'name', 'sku']);
        $productIds = $products->pluck('id')->all();
        $variantIds = Variant::query()->where('is_active', true)->pluck('id')->all();

        $total = 60;

        for ($i = 0; $i < $total; $i++) {
            $customer = $customers[$i % count($customers)];
            $status = $statuses[$i % count($statuses)];
            $orderNumber = 'ORD-2608-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            $order = Order::firstOrCreate(
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

            // Keep the seeder idempotent: only attach items once.
            if ($order->items()->exists() || empty($productIds)) {
                continue;
            }

            $itemCount = 1 + ($i % 3);
            for ($j = 0; $j < $itemCount; $j++) {
                $productId = $this->pickProductId($productIds);
                if ($productId === null) {
                    continue;
                }

                $product = $products->firstWhere('id', $productId);
                $quantity = random_int(1, 20);
                $unitPrice = round(5 + random_int(1, 200), 2);
                $variantId = (! empty($variantIds) && random_int(1, 4) === 1)
                    ? $variantIds[array_rand($variantIds)]
                    : null;

                OrderItem::create([
                    'uuid' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'name' => $product->name ?? 'Product',
                    'sku' => $product->sku ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'base_price' => $unitPrice,
                    'total' => round($quantity * $unitPrice, 2),
                    'currency' => 'USD',
                    'created_at' => $order->created_at,
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info("Seeded orders and order items for tenant {$tenant->id}.");
    }

    /**
     * Bias the selection toward the first third of products so the seeded
     * catalog produces a meaningful "best sellers" ranking.
     */
    private function pickProductId(array $productIds): ?int
    {
        $count = count($productIds);
        if ($count === 0) {
            return null;
        }

        // 60% of the time, pick from the most popular bucket.
        if (random_int(1, 10) <= 6) {
            $lastIndex = max(0, (int) floor($count / 3) - 1);
            return $productIds[random_int(0, $lastIndex)];
        }

        return $productIds[random_int(0, $count - 1)];
    }
}
