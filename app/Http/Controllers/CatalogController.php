<?php

namespace App\Http\Controllers;

use App\Models\StudyItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function kana(Request $request): JsonResponse
    {
        $data = $request->validate(['script' => ['required', 'in:HIRAGANA,KATAKANA']]);

        return $this->catalog($request, 'KANA', ['script' => $data['script']]);
    }

    public function kanji(Request $request): JsonResponse
    {
        $data = $request->validate(['jlpt' => ['nullable', 'in:N5,N4,N3,N2,N1']]);

        return $this->catalog($request, 'KANJI', ['jlpt_level' => $data['jlpt'] ?? null]);
    }

    public function words(Request $request): JsonResponse
    {
        $data = $request->validate(['jlpt' => ['nullable', 'in:N5,N4,N3,N2,N1']]);

        return $this->catalog($request, 'WORD', ['jlpt_level' => $data['jlpt'] ?? null]);
    }

    public function showKanji(Request $request, string $kanji): JsonResponse
    {
        $item = StudyItem::query()
            ->where(['type' => 'KANJI', 'glyph' => $kanji])
            ->with(['progress' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->firstOrFail();
        $examples = StudyItem::query()->where('type', 'WORD')->where('surface', 'like', "%{$kanji}%")->limit(2)->get();

        return response()->json($this->item($item) + [
            'examples' => $examples->map(fn (StudyItem $word) => [
                'surface' => $word->surface,
                'reading' => $word->reading,
                'meaningsEs' => $word->meanings_es,
                'meaningsEn' => $word->meanings_en,
            ])->values(),
        ]);
    }

    private function catalog(Request $request, string $type, array $filters): JsonResponse
    {
        $items = StudyItem::query()
            ->where('type', $type)
            ->when($filters['script'] ?? null, fn ($query, $script) => $query->where('script', $script))
            ->when($filters['jlpt_level'] ?? null, fn ($query, $level) => $query->where('jlpt_level', $level))
            ->with(['progress' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->get()
            ->map(fn (StudyItem $item) => $this->item($item));

        return response()->json($items);
    }

    private function item(StudyItem $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'jlptLevel' => $item->jlpt_level,
            'script' => $item->script,
            'glyph' => $item->glyph,
            'surface' => $item->surface,
            'meaningsEs' => $item->meanings_es,
            'meaningsEn' => $item->meanings_en,
            'romaji' => $item->type === 'KANA' ? $item->romaji : null,
            'gridRow' => $item->type === 'KANA' ? (int) $item->kana_row : null,
            'gridColumn' => $item->type === 'KANA' ? (int) $item->kana_column : null,
            'onyomi' => $item->type === 'KANJI' ? $item->onyomi : null,
            'kunyomi' => $item->type === 'KANJI' ? $item->kunyomi : null,
            'strokeCount' => $item->type === 'KANJI' ? $item->stroke_count : null,
            'reading' => $item->type === 'WORD' ? $item->reading : null,
            'progress' => $item->progress->first()?->only([
                'status', 'times_seen', 'times_correct', 'next_review_at',
            ]),
        ];
    }
}
