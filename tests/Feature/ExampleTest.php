<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

test('the application returns a successful response', function () {
    if (!\Illuminate\Support\Facades\Schema::hasTable('languages')) {
        \Illuminate\Support\Facades\Schema::create('languages', function ($table) {
            $table->string('code');
            $table->boolean('is_active')->default(false);
        });
    }

    $response = $this->get('/');

    $response->assertStatus(200);
});
