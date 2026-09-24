<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\DailyActivity;
use App\Models\LeaderboardEntry;
use App\Models\Question;
use App\Models\StudyItem;
use App\Models\StudySession;
use App\Models\UserItemProgress;
use App\Support\KanaConverter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SessionController extends Controller
{
    private const CHOICE_EXERCISES = ['WORD_READING', 'WORD_WRITING'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exercise' => ['required', 'in:KANA_READING,KANA_WRITING,KANJI_READING,WORD_READING,WORD_WRITING'],
            'format' => ['nullable', 'in:CARD,CHOICE'],
            'length' => ['nullable', 'integer', 'min:1', 'max:50'],
            'script' => ['nullable', 'in:HIRAGANA,KATAKANA'],
            'itemIds' => ['nullable', 'array', 'max:50'],
            'itemIds.*' => ['uuid'],
            'jlpt' => ['nullable', 'in:N5,N4,N3,N2,N1'],
            'lang' => ['nullable', 'in:es,en'],
        ]);
        $format = $data['format'] ?? 'CARD';
        if ($format === 'CHOICE' && ! in_array($data['exercise'], self::CHOICE_EXERCISES, true)) {
            throw ValidationException::withMessages(['format' => ['Multiple choice is only available for word exercises.']]);
        }
        $config = $this->config($data['exercise'], $data['script'] ?? null, $data['lang'] ?? 'es');
        $length = $data['length'] ?? 10;
        $items = StudyItem::query()->where($config['filters'])
            ->when($data['jlpt'] ?? null, fn ($query, $jlpt) => $query->where('jlpt_level', $jlpt))
            ->when($data['itemIds'] ?? null, fn ($query, $ids) => $query->whereIn('id', $ids))
            ->when(! ($data['itemIds'] ?? null), fn ($query) => $query->inRandomOrder()->limit($length))
            ->get();

        if ($data['itemIds'] ?? null) {
            if ($items->count() !== count($data['itemIds'])) {
                throw ValidationException::withMessages(['itemIds' => ['Each item must exist and match the exercise.']]);
            }
            if ($data['exercise'] === 'KANJI_READING') {
                $examples = StudyItem::query()->where('type', 'WORD')->get()->filter(
                    fn (StudyItem $word) => $items->contains(fn (StudyItem $kanji) => str_contains($word->surface ?? '', $kanji->glyph)),
                );
                $items = $items->concat($examples)->unique('id')->take($length)->values();
            }
            $items = $items->shuffle()->take($length)->values();
            $length = $items->count();
        }

        if ($items->count() < $length) {
            throw ValidationException::withMessages([
                'exercise' => ['There are not enough study items for this exercise.'],
            ]);
        }

        $pool = $format === 'CHOICE' ? StudyItem::query()->where($config['filters'])->get() : null;

        $session = DB::transaction(function () use ($request, $data, $config, $items, $length, $format, $pool): StudySession {
            $session = $request->user()->sessions()->create([
                'exercise' => $data['exercise'],
                'format' => $format,
                'length' => $length,
                'started_at' => now(),
            ]);

            $items->each(function (StudyItem $item, int $position) use ($session, $config, $data, $pool): void {
                $session->questions()->create([
                    'study_item_id' => $item->id,
                    'position' => $position + 1,
                    'prompt' => $config['prompt']($item),
                    'direction' => $config['direction'],
                    'accepted_scripts' => $config['accepted_scripts'],
                    'choices' => $pool ? $this->choicesFor($data['exercise'], $item, $pool) : null,
                ]);
            });

            return $session;
        });

        return response()->json($this->sessionPayload($session->load('questions')), 201);
    }

    public function show(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        return response()->json($this->sessionPayload($session->load('questions')));
    }

    public function answer(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);
        $data = $request->validate([
            'questionId' => ['required', 'uuid'],
            'rawInput' => ['present', 'nullable', 'string', 'max:255'],
            'clientAnsweredAt' => ['nullable', 'date'],
            'idempotencyKey' => ['required', 'string', 'max:255'],
        ]);

        return $this->submit($session, $data);
    }

    public function sync(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);
        $data = $request->validate(['answers' => ['required', 'array', 'max:50']]);
        $results = [];
        foreach ($data['answers'] as $answer) {
            $validator = Validator::make($answer, [
                'questionId' => ['required', 'uuid'],
                'rawInput' => ['present', 'nullable', 'string', 'max:255'],
                'clientAnsweredAt' => ['nullable', 'date'],
                'idempotencyKey' => ['required', 'string', 'max:255'],
            ]);
            if ($validator->fails()) {
                $results[] = ['error' => ['status' => 422, 'message' => $validator->errors()->first()]];

                continue;
            }
            try {
                $response = $this->submit($session, $validator->validated());
                $body = $response->getData(true);
                $results[] = $response->getStatusCode() === 200 ? $body : ['error' => ['status' => $response->getStatusCode(), 'message' => $body['message'] ?? 'Request failed.']];
            } catch (ModelNotFoundException) {
                $results[] = ['error' => ['status' => 404, 'message' => 'Question not found.']];
            } catch (HttpExceptionInterface $exception) {
                $results[] = ['error' => ['status' => $exception->getStatusCode(), 'message' => $exception->getMessage()]];
            }
        }

        return response()->json(['results' => $results]);
    }

    private function submit(StudySession $session, array $data): JsonResponse
    {
        $question = $session->questions()->with(['item', 'answer', 'session'])->findOrFail($data['questionId']);

        $idempotencyKey = hash('sha256', $session->user_id.'|'.$data['idempotencyKey']);

        if ($answer = Answer::where('idempotency_key', $idempotencyKey)->first()) {
            if ($answer->question_id !== $question->id) {
                return response()->json(['message' => 'Idempotency key belongs to another answer.'], 409);
            }

            return response()->json($this->answerPayload($question, $answer));
        }

        if ($question->answer) {
            return response()->json(['message' => 'Question already answered.'], 409);
        }

        $rawInput = trim($data['rawInput'] ?? '');

        if ($rawInput === '') {
            return response()->json(['feedbackCode' => 'EMPTY']);
        }

        $detectedScript = KanaConverter::detect($rawInput);

        if (! $detectedScript && ($question->choices || $question->direction === 'MEANING_TO_WORD')) {
            $detectedScript = 'HIRAGANA';
        }

        if (! $detectedScript || ! in_array($detectedScript, $question->accepted_scripts, true)) {
            return response()->json(array_filter(['detectedScript' => $detectedScript, 'feedbackCode' => 'WRONG_SCRIPT']));
        }

        $normalized = KanaConverter::normalize($rawInput, $question->direction === 'MEANING_TO_WORD' ? 'KANJI' : $detectedScript);
        $isCorrect = $this->isCorrect($question, $rawInput, $normalized);
        $xpAwarded = $isCorrect ? $this->nextXp($session) : 0;

        $answer = DB::transaction(function () use ($question, $rawInput, $detectedScript, $normalized, $isCorrect, $xpAwarded, $idempotencyKey, $session): Answer {
            $answer = $question->answer()->create([
                'raw_input' => $rawInput,
                'detected_script' => $detectedScript,
                'normalized' => $normalized,
                'is_correct' => $isCorrect,
                'xp_awarded' => $xpAwarded,
                'answered_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);
            $this->updateProgress($session, $question->item, $isCorrect);
            $session->increment('correct_count', $isCorrect ? 1 : 0);
            $session->increment('xp_earned', $xpAwarded);

            return $answer;
        });

        $question->refresh()->load('answer');

        return response()->json($this->answerPayload($question, $answer));
    }

    public function complete(Request $request, StudySession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        if (! $session->finished_at) {
            if ($session->questions()->doesntHave('answer')->exists()) {
                return response()->json(['message' => 'Answer every question before completing the session.'], 422);
            }

            $session->update(['finished_at' => now()]);
            $stats = $request->user()->stats;
            $stats->increment('xp_total', $session->xp_earned);
            $stats->increment('xp_into_level', $session->xp_earned);
            $streakDays = $stats->last_study_date?->isToday()
                ? $stats->streak_days
                : ($stats->last_study_date?->isYesterday() ? $stats->streak_days + 1 : 1);
            $stats->update(['last_study_date' => today(), 'streak_days' => $streakDays]);
            $this->updateActivity($session);
            $this->updateLeaderboard($session);
        }

        return response()->json([
            'correctCount' => $session->correct_count,
            'xpEarned' => $session->xp_earned,
            'finishedAt' => $session->finished_at,
            'length' => $session->length,
            'streakDays' => $request->user()->stats->streak_days,
        ]);
    }

    private function config(string $exercise, ?string $script, string $lang = 'es'): array
    {
        return match ($exercise) {
            'KANA_READING' => [
                'filters' => ['type' => 'KANA', 'script' => $script ?? 'HIRAGANA'],
                'direction' => 'KANA_TO_ROMAJI',
                'accepted_scripts' => ['ROMAJI'],
                'prompt' => fn (StudyItem $item): string => $item->glyph,
            ],
            'KANA_WRITING' => [
                'filters' => ['type' => 'KANA', 'script' => $script ?? 'HIRAGANA'],
                'direction' => 'ROMAJI_TO_KANA',
                'accepted_scripts' => [$script ?? 'HIRAGANA'],
                'prompt' => fn (StudyItem $item): string => $item->romaji[0],
            ],
            'WORD_WRITING' => [
                'filters' => ['type' => 'WORD', 'jlpt_level' => 'N5'],
                'direction' => 'MEANING_TO_WORD',
                'accepted_scripts' => ['KANJI', 'HIRAGANA', 'KATAKANA'],
                'prompt' => fn (StudyItem $item): string => implode('; ', ($lang === 'en' ? $item->meanings_en : null) ?? $item->meanings_es),
            ],
            'WORD_READING' => [
                'filters' => ['type' => 'WORD', 'jlpt_level' => 'N5'],
                'direction' => 'KANJI_TO_READING',
                'accepted_scripts' => ['ROMAJI', 'HIRAGANA', 'KATAKANA'],
                'prompt' => fn (StudyItem $item): string => $item->surface,
            ],
            default => [
                'filters' => ['type' => 'KANJI', 'jlpt_level' => 'N5'],
                'direction' => 'KANJI_TO_READING',
                'accepted_scripts' => ['ROMAJI', 'HIRAGANA', 'KATAKANA'],
                'prompt' => fn (StudyItem $item): string => $item->glyph ?? $item->surface,
            ],
        };
    }

    private function isCorrect(Question $question, string $rawInput, string $normalized): bool
    {
        if ($question->direction === 'KANA_TO_ROMAJI') {
            return in_array(strtolower($rawInput), $question->item->romaji, true);
        }

        if ($question->direction === 'ROMAJI_TO_KANA') {
            return $normalized === $question->item->glyph;
        }

        if ($question->direction === 'MEANING_TO_WORD') {
            return in_array($normalized, $this->writtenForms($question->item, StudyItem::where('type', 'WORD')->get()), true);
        }

        return in_array($normalized, array_map(fn (string $reading): string => mb_convert_kana($reading, 'c', 'UTF-8'), $this->readings($question->item)), true);
    }

    private function readings(StudyItem $item): array
    {
        if ($item->type === 'WORD') {
            return $this->variants($item->reading);
        }

        return array_merge($item->onyomi ?? [], $item->kunyomi ?? []);
    }

    private function variants(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\\s*\\/\\s*/', $value))));
    }

    /** Every accepted written form: words with the same meanings are interchangeable. */
    private function writtenForms(StudyItem $item, Collection $words): array
    {
        return $words->filter(fn (StudyItem $word): bool => $word->id === $item->id || $word->meanings_es === $item->meanings_es)
            ->flatMap(fn (StudyItem $word): array => $this->variants($word->surface))
            ->unique()->values()->all();
    }

    private function choicesFor(string $exercise, StudyItem $item, Collection $pool): array
    {
        $value = fn (StudyItem $word): string => $this->variants($exercise === 'WORD_WRITING' ? $word->surface : $word->reading)[0];
        $accepted = $exercise === 'WORD_WRITING' ? $this->writtenForms($item, $pool) : $this->readings($item);
        $distractors = $pool->map($value)->unique()->reject(fn (string $choice): bool => in_array($choice, $accepted, true))->shuffle()->take(3);

        return $distractors->push($value($item))->shuffle()->values()->all();
    }

    private function nextXp(StudySession $session): int
    {
        $streak = 1;

        foreach ($session->questions()->with('answer')->get()->sortByDesc('answer.answered_at') as $question) {
            if (! $question->answer || ! $question->answer->is_correct) {
                break;
            }

            $streak++;
        }

        return [1, 2, 3, 5, 8][min($streak - 1, 4)];
    }

    private function updateProgress(StudySession $session, StudyItem $item, bool $isCorrect): void
    {
        $progress = UserItemProgress::firstOrNew([
            'user_id' => $session->user_id,
            'study_item_id' => $item->id,
        ]);
        $progress->times_seen++;

        if ($isCorrect) {
            $progress->times_correct++;
            $progress->status = $progress->times_correct >= 5 ? 'LEARNED' : 'LEARNING';
            $progress->next_review_at = now()->addDays([1, 2, 3, 5, 8, 13, 21][min($progress->times_correct - 1, 6)]);
        }

        $progress->save();
    }

    private function updateActivity(StudySession $session): void
    {
        $activity = DailyActivity::firstOrNew(['user_id' => $session->user_id, 'date' => today()]);
        $activity->goal_minutes ??= $session->user->settings->daily_goal_minutes;
        $activity->minutes_studied += max(1, $session->started_at->diffInMinutes(now()));
        $activity->goal_met = $activity->minutes_studied >= $activity->goal_minutes;
        $activity->save();
    }

    private function updateLeaderboard(StudySession $session): void
    {
        $entry = LeaderboardEntry::firstOrNew([
            'week_start' => now()->startOfWeek(),
            'user_id' => $session->user_id,
        ]);
        $entry->display_name = $session->user->nickname;
        $entry->xp += $session->xp_earned;
        $entry->save();
    }

    private function answerPayload(Question $question, Answer $answer): array
    {
        return [
            'questionId' => $question->id,
            'isCorrect' => $answer->is_correct,
            'detectedScript' => $answer->detected_script,
            'normalized' => $answer->normalized,
            'correctAnswer' => [
                'kana' => $question->item->type === 'KANA' ? $question->item->glyph : $question->item->reading,
                'romaji' => $question->item->type === 'KANA' ? $question->item->romaji[0] : null,
                'surface' => $question->item->type === 'WORD' ? $question->item->surface : null,
            ],
            'feedbackCode' => $answer->is_correct ? 'CORRECT' : 'INCORRECT',
            'xpAwarded' => $answer->xp_awarded,
            'nextReviewAt' => UserItemProgress::where('user_id', $question->session->user_id)->where('study_item_id', $question->study_item_id)->value('next_review_at'),
            'progress' => [
                'index' => $question->position,
                'correctCount' => $question->session->correct_count,
            ],
        ];
    }

    private function sessionPayload(StudySession $session): array
    {
        return [
            'id' => $session->id,
            'exercise' => $session->exercise,
            'format' => $session->format,
            'length' => $session->length,
            'startedAt' => $session->started_at,
            'finishedAt' => $session->finished_at,
            'questions' => $session->questions->map(fn (Question $question) => [
                'id' => $question->id,
                'itemId' => $question->study_item_id,
                'index' => $question->position,
                'prompt' => $question->prompt,
                'direction' => $question->direction,
                'acceptedScripts' => $question->accepted_scripts,
                ...($question->choices ? ['choices' => $question->choices] : []),
            ])->values(),
        ];
    }

    private function authorizeSession(Request $request, StudySession $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 404);
    }
}
