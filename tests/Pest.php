<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

var_dump('PEST_ROOT_LOADED');
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Unit');
