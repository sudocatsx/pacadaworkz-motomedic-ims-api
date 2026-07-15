<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\Tutorial\UpdateTutorialPreferencesRequest;
use App\Http\Requests\Tutorial\UpdateTutorialProgressRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TutorialController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $preference = $user->tutorialPreference;

        return response()->json(['success' => true, 'data' => [
            'progress' => $user->tutorialProgress()->orderBy('tutorial_key')->get(),
            'preferences' => ['welcome_prompt_seen_at' => $preference?->welcome_prompt_seen_at?->toISOString()],
        ]]);
    }

    public function updatePreferences(UpdateTutorialPreferencesRequest $request): JsonResponse
    {
        $preference = $request->user()->tutorialPreference()->firstOrCreate();
        if (! $preference->welcome_prompt_seen_at) {
            $preference->update(['welcome_prompt_seen_at' => now()]);
        }

        return response()->json(['success' => true, 'data' => ['welcome_prompt_seen_at' => $preference->fresh()->welcome_prompt_seen_at?->toISOString()]]);
    }

    public function updateProgress(UpdateTutorialProgressRequest $request, string $tutorialKey): JsonResponse
    {
        $definition = config("tutorials.{$tutorialKey}");
        if (! $definition) {
            throw ValidationException::withMessages(['tutorial_key' => ['The selected tutorial is invalid.']]);
        }

        $data = $request->validated();
        if ($data['content_version'] !== $definition['version']) {
            throw ValidationException::withMessages(['content_version' => ['The tutorial version is invalid.']]);
        }
        if ($data['current_step'] >= $definition['steps']) {
            throw ValidationException::withMessages(['current_step' => ['The current step is outside the tutorial bounds.']]);
        }

        $restart = (bool) ($data['restart'] ?? false);
        if ($restart && ($data['status'] !== 'in_progress' || $data['current_step'] !== 0)) {
            throw ValidationException::withMessages(['restart' => ['A restart must begin in progress at step zero.']]);
        }

        $progress = $request->user()->tutorialProgress()->firstOrNew([
            'tutorial_key' => $tutorialKey,
            'content_version' => $definition['version'],
        ]);
        $now = now();
        $progress->fill([
            'status' => $data['status'],
            'current_step' => $data['current_step'],
            'started_at' => ($restart || ! $progress->started_at) ? $now : $progress->started_at,
            'completed_at' => $data['status'] === 'completed' ? $now : null,
            'skipped_at' => $data['status'] === 'skipped' ? $now : null,
        ])->save();

        return response()->json(['success' => true, 'data' => $progress->fresh()]);
    }
}
