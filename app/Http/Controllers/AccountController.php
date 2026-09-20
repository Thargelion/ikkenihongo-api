<?php

namespace App\Http\Controllers;

use App\Models\DailyActivity;
use App\Models\LeaderboardEntry;
use App\Models\StudyItem;
use App\Models\UserItemProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AccountController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load(['settings', 'stats']));
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json($request->user()->stats);
    }

    public function activity(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return response()->json(
            DailyActivity::query()
                ->where('user_id', $request->user()->id)
                ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('date', '>=', $from))
                ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
                ->orderBy('date')
                ->get(),
        );
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dailyGoalMinutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'remindersEnabled' => ['sometimes', 'boolean'],
            'soundEnabled' => ['sometimes', 'boolean'],
            'preferredScript' => ['sometimes', 'in:ROMAJI,HIRAGANA,KATAKANA'],
        ]);
        $request->user()->settings->update([
            'daily_goal_minutes' => $data['dailyGoalMinutes'] ?? $request->user()->settings->daily_goal_minutes,
            'reminders_enabled' => $data['remindersEnabled'] ?? $request->user()->settings->reminders_enabled,
            'sound_enabled' => $data['soundEnabled'] ?? $request->user()->settings->sound_enabled,
            'preferred_script' => $data['preferredScript'] ?? $request->user()->settings->preferred_script,
        ]);

        return response()->json($request->user()->settings->fresh());
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $data = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $now = now(config('app.timezone'));
        $weekStart = $now->copy()->startOfWeek();
        $entries = LeaderboardEntry::whereDate('week_start', $weekStart)
            ->orderByDesc('xp')
            ->get()
            ->values()
            ->map(fn (LeaderboardEntry $entry, int $index) => [
                'displayName' => $entry->display_name,
                'xp' => $entry->xp,
                'rank' => $index + 1,
                'isCurrentUser' => $entry->user_id === $request->user()->id,
            ]);

        return response()->json([
            'entries' => $entries->take($data['limit'] ?? 50)->values(),
            'you' => $entries->firstWhere('isCurrentUser', true),
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekStart->copy()->endOfWeek()->toDateString(),
            'daysLeft' => max(0, 6 - $now->dayOfWeekIso + 1),
        ]);
    }

    public function upgrade(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_guest, 409, 'Only guest accounts can be upgraded.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        $user->update($data + ['password' => Hash::make($data['password']), 'is_guest' => false]);

        return response()->json($user->fresh());
    }

    public function destroyProgress(Request $request): Response
    {
        $user = $request->user();
        DB::transaction(function () use ($user): void {
            UserItemProgress::where('user_id', $user->id)->delete();
            $user->sessions()->delete();
            DailyActivity::where('user_id', $user->id)->delete();
            LeaderboardEntry::where('user_id', $user->id)->delete();
            $user->stats()->update(['level' => 1, 'xp_total' => 0, 'xp_into_level' => 0, 'streak_days' => 0, 'last_study_date' => null]);
        });

        return response()->noContent();
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate(['jlpt' => ['nullable', 'in:N5,N4,N3,N2,N1']]);
        $user = $request->user();
        $level = $data['jlpt'] ?? 'N5';
        $counts = fn (string $type, array $filters = []) => StudyItem::where(['type' => $type] + $filters)->count();
        $learned = fn (string $type, array $filters = []) => UserItemProgress::query()->where('user_id', $user->id)->where('status', 'LEARNED')->whereHas('item', fn ($query) => $query->where(['type' => $type] + $filters))->count();
        $rank = LeaderboardEntry::whereDate('week_start', now(config('app.timezone'))->startOfWeek())
            ->where('xp', '>', 0)->orderByDesc('xp')->pluck('user_id')->search($user->id);

        return response()->json([
            'hiragana' => ['learned' => $learned('KANA', ['script' => 'HIRAGANA']), 'total' => $counts('KANA', ['script' => 'HIRAGANA'])],
            'katakana' => ['learned' => $learned('KANA', ['script' => 'KATAKANA']), 'total' => $counts('KANA', ['script' => 'KATAKANA'])],
            'kanji' => ['learned' => $learned('KANJI', ['jlpt_level' => $level]), 'total' => $counts('KANJI', ['jlpt_level' => $level])],
            'words' => ['total' => $counts('WORD', ['jlpt_level' => $level])],
            'weeklyRank' => $rank === false ? null : $rank + 1,
            'dailyGoalMinutes' => $user->settings->daily_goal_minutes,
        ]);
    }
}
