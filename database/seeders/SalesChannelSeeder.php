<?php

namespace Database\Seeders;

use App\Enums\SalesChannel;
use App\Models\SalesChannel as SalesChannelModel;
use Illuminate\Database\Seeder;

class SalesChannelSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        SalesChannelModel::query()->upsert(
            collect(SalesChannel::ALL)->values()->map(fn (string $code, int $index) => [
                'code' => $code,
                'name' => SalesChannel::label($code),
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
            ['code'],
            ['name', 'is_active', 'sort_order', 'updated_at'],
        );
    }
}
