<?php

use App\Services\Ai\TokenBudgetService;

it('reports the full budget before anything is spent', function () {
    config([
        'ai.limits.visitor_daily_tokens' => 100,
        'ai.limits.global_daily_tokens' => 1000,
    ]);

    $budget = app(TokenBudgetService::class);

    expect($budget->visitorRemaining('visitor-1'))->toBe(100)
        ->and($budget->hasBudget('visitor-1'))->toBeTrue();
});

it('decrements the visitor budget as tokens are recorded', function () {
    config([
        'ai.limits.visitor_daily_tokens' => 100,
        'ai.limits.global_daily_tokens' => 1000,
    ]);

    $budget = app(TokenBudgetService::class);
    $budget->record('visitor-1', 60);

    expect($budget->visitorRemaining('visitor-1'))->toBe(40)
        ->and($budget->hasBudget('visitor-1'))->toBeTrue();

    $budget->record('visitor-1', 50);

    expect($budget->visitorRemaining('visitor-1'))->toBe(0)
        ->and($budget->hasBudget('visitor-1'))->toBeFalse();
});

it('keeps separate budgets per visitor', function () {
    config([
        'ai.limits.visitor_daily_tokens' => 100,
        'ai.limits.global_daily_tokens' => 1000,
    ]);

    $budget = app(TokenBudgetService::class);
    $budget->record('visitor-1', 100);

    expect($budget->hasBudget('visitor-1'))->toBeFalse()
        ->and($budget->hasBudget('visitor-2'))->toBeTrue();
});

it('enforces the global budget across visitors', function () {
    config([
        'ai.limits.visitor_daily_tokens' => 1000,
        'ai.limits.global_daily_tokens' => 50,
    ]);

    $budget = app(TokenBudgetService::class);
    $budget->record('visitor-1', 50);

    expect($budget->globalRemaining())->toBe(0)
        ->and($budget->hasBudget('visitor-2'))->toBeFalse();
});
