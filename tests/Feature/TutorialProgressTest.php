<?php

use App\Models\ActivityLog;
use App\Models\TutorialProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tutorial endpoints require authentication', function () {
    $this->getJson('/api/v1/tutorials/progress')->assertUnauthorized();
    $this->patchJson('/api/v1/tutorials/preferences', ['welcome_prompt_seen' => true])->assertUnauthorized();
    $this->putJson('/api/v1/tutorials/pos-first-sale/progress', [])->assertUnauthorized();
});

test('welcome prompt dismissal persists once', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')->getJson('/api/v1/tutorials/progress')
        ->assertOk()->assertJsonPath('data.preferences.welcome_prompt_seen_at', null);

    $first = $this->actingAs($user, 'api')->patchJson('/api/v1/tutorials/preferences', ['welcome_prompt_seen' => true])
        ->assertOk()->json('data.welcome_prompt_seen_at');
    $second = $this->actingAs($user, 'api')->patchJson('/api/v1/tutorials/preferences', ['welcome_prompt_seen' => true])
        ->assertOk()->json('data.welcome_prompt_seen_at');

    expect($second)->toBe($first);
});

test('users can only read and update their own progress', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    TutorialProgress::unguarded(fn () => TutorialProgress::create([
        'user_id' => $other->id, 'tutorial_key' => 'pos-first-sale', 'content_version' => 1,
        'status' => 'completed', 'current_step' => 5,
    ]));

    $this->actingAs($user, 'api')->putJson('/api/v1/tutorials/pos-first-sale/progress', [
        'content_version' => 1, 'status' => 'in_progress', 'current_step' => 2,
    ])->assertOk();

    $this->actingAs($user, 'api')->getJson('/api/v1/tutorials/progress')
        ->assertOk()->assertJsonCount(1, 'data.progress')->assertJsonPath('data.progress.0.current_step', 2);
    expect(TutorialProgress::where('user_id', $other->id)->first()->status)->toBe('completed');
});

test('progress supports skip restart completion and replay transitions', function () {
    $user = User::factory()->create();
    $url = '/api/v1/tutorials/pos-first-sale/progress';

    $this->actingAs($user, 'api')->putJson($url, ['content_version' => 1, 'status' => 'skipped', 'current_step' => 3])
        ->assertOk()->assertJsonPath('data.status', 'skipped');
    expect($user->tutorialProgress()->first()->skipped_at)->not->toBeNull();

    $this->actingAs($user, 'api')->putJson($url, ['content_version' => 1, 'status' => 'in_progress', 'current_step' => 0, 'restart' => true])
        ->assertOk()->assertJsonPath('data.skipped_at', null);

    $this->actingAs($user, 'api')->putJson($url, ['content_version' => 1, 'status' => 'completed', 'current_step' => 5])
        ->assertOk()->assertJsonPath('data.status', 'completed');
    expect($user->tutorialProgress()->first()->completed_at)->not->toBeNull();

    $this->actingAs($user, 'api')->putJson($url, ['content_version' => 1, 'status' => 'in_progress', 'current_step' => 0, 'restart' => true])
        ->assertOk()->assertJsonPath('data.completed_at', null);
});

test('tutorial registry and progress bounds are validated', function () {
    $user = User::factory()->create();
    $payload = ['content_version' => 1, 'status' => 'in_progress', 'current_step' => 0];

    $this->actingAs($user, 'api')->putJson('/api/v1/tutorials/unknown/progress', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('tutorial_key');
    $this->actingAs($user, 'api')->putJson('/api/v1/tutorials/pos-first-sale/progress', [...$payload, 'content_version' => 2])
        ->assertUnprocessable()->assertJsonValidationErrors('content_version');
    $this->actingAs($user, 'api')->putJson('/api/v1/tutorials/pos-first-sale/progress', [...$payload, 'current_step' => 6])
        ->assertUnprocessable()->assertJsonValidationErrors('current_step');
    $this->actingAs($user, 'api')->putJson('/api/v1/tutorials/pos-first-sale/progress', [...$payload, 'status' => 'paused'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');
});

test('all released tutorials accept their registered version and step bounds', function () {
    $user = User::factory()->create();
    $definitions = config('tutorials');

    expect($definitions)->toHaveCount(8);

    foreach ($definitions as $key => $definition) {
        $url = "/api/v1/tutorials/{$key}/progress";

        $this->actingAs($user, 'api')->putJson($url, [
            'content_version' => $definition['version'],
            'status' => 'in_progress',
            'current_step' => 0,
            'restart' => true,
        ])->assertOk()->assertJsonPath('data.tutorial_key', $key);

        $this->actingAs($user, 'api')->putJson($url, [
            'content_version' => $definition['version'],
            'status' => 'completed',
            'current_step' => $definition['steps'] - 1,
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->actingAs($user, 'api')->putJson($url, [
            'content_version' => $definition['version'],
            'status' => 'in_progress',
            'current_step' => $definition['steps'],
        ])->assertUnprocessable()->assertJsonValidationErrors('current_step');
    }

    expect($user->tutorialProgress()->count())->toBe(8);
});

test('tutorial progress changes are not added to activity logs', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')->patchJson('/api/v1/tutorials/preferences', ['welcome_prompt_seen' => true])->assertOk();
    $this->actingAs($user, 'api')->putJson('/api/v1/tutorials/pos-first-sale/progress', [
        'content_version' => 1, 'status' => 'in_progress', 'current_step' => 0,
    ])->assertOk();

    expect(ActivityLog::query()->count())->toBe(0);
});
