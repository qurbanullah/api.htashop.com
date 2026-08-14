<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

var_dump(Illuminate\Support\Facades\Schema::hasTable('authors'));
var_dump(Illuminate\Support\Facades\Schema::hasTable('manuscripts'));
