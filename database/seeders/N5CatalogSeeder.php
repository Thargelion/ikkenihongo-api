<?php

namespace Database\Seeders;

use App\Models\StudyItem;
use Illuminate\Database\Seeder;

class N5CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedKana('n5-hiragana.tres', 'HIRAGANA');
        $this->seedKana('n5-katakana.tres', 'KATAKANA');
        $this->seedKanji();
        $this->seedWords();
        $this->seedKanjiExamples();
    }

    private function seedKana(string $file, string $script): void
    {
        foreach ($this->rows($file) as [$romaji, $glyph]) {
            StudyItem::updateOrCreate(
                ['source_key' => "kana:$script:$glyph"],
                [
                    'type' => 'KANA',
                    'script' => $script,
                    'glyph' => $glyph,
                    'romaji' => $this->romajiVariants($romaji),
                    ...$this->kanaPosition($romaji),
                ],
            );
        }
    }

    private function seedKanji(): void
    {
        foreach ($this->rows('n5-kanji.tres') as [$glyph, $meanings]) {
            [$onyomi, $kunyomi, $strokeCount] = $this->kanjiData()[$glyph];
            StudyItem::updateOrCreate(
                ['source_key' => "kanji:$glyph"],
                [
                    'type' => 'KANJI',
                    'jlpt_level' => 'N5',
                    'glyph' => $glyph,
                    'meanings_es' => $this->meanings($meanings),
                    'onyomi' => $onyomi,
                    'kunyomi' => $kunyomi,
                    'stroke_count' => $strokeCount,
                ],
            );
        }
    }

    private function seedWords(): void
    {
        foreach ($this->rows('n5-vocabulary.tres') as [$surface, $reading, $meanings]) {
            StudyItem::updateOrCreate(
                ['source_key' => 'word:'.sha1("$surface|$reading|$meanings")],
                [
                    'type' => 'WORD',
                    'jlpt_level' => 'N5',
                    'surface' => $surface ?: $reading,
                    'reading' => $reading,
                    'meanings_es' => $this->meanings($meanings),
                ],
            );
        }
    }

    private function seedKanjiExamples(): void
    {
        foreach ([
            '中' => [['中', 'なか', 'dentro'], ['中学校', 'ちゅうがっこう', 'escuela secundaria']],
            '何' => [['何', 'なに', 'qué'], ['何時', 'なんじ', 'qué hora']],
            '円' => [['円', 'えん', 'yen'], ['百円', 'ひゃくえん', 'cien yenes']],
            '北' => [['北', 'きた', 'norte'], ['北口', 'きたぐち', 'salida norte']],
            '千' => [['千', 'せん', 'mil'], ['千円', 'せんえん', 'mil yenes']],
            '南' => [['南', 'みなみ', 'sur'], ['南口', 'みなみぐち', 'salida sur']],
            '友' => [['友達', 'ともだち', 'amigo'], ['友人', 'ゆうじん', 'amistad']],
            '右' => [['右', 'みぎ', 'derecha'], ['右手', 'みぎて', 'mano derecha']],
            '土' => [['土', 'つち', 'tierra'], ['土曜日', 'どようび', 'sábado']],
            '天' => [['天', 'てん', 'cielo'], ['天気', 'てんき', 'clima']],
            '山' => [['山', 'やま', 'montaña'], ['火山', 'かざん', 'volcán']],
            '川' => [['川', 'かわ', 'río'], ['川口', 'かわぐち', 'boca del río']],
            '左' => [['左', 'ひだり', 'izquierda'], ['左手', 'ひだりて', 'mano izquierda']],
            '東' => [['東', 'ひがし', 'este'], ['東京', 'とうきょう', 'Tokio']],
            '校' => [['学校', 'がっこう', 'escuela'], ['校長', 'こうちょう', 'director escolar']],
            '火' => [['火', 'ひ', 'fuego'], ['火曜日', 'かようび', 'martes']],
            '西' => [['西', 'にし', 'oeste'], ['西口', 'にしぐち', 'salida oeste']],
            '語' => [['日本語', 'にほんご', 'japonés'], ['語学', 'ごがく', 'idiomas']],
            '読' => [['読む', 'よむ', 'leer'], ['読書', 'どくしょ', 'lectura']],
            '長' => [['長い', 'ながい', 'largo'], ['校長', 'こうちょう', 'director escolar']],
            '間' => [['時間', 'じかん', 'tiempo'], ['間', 'あいだ', 'intervalo']],
            '雨' => [['雨', 'あめ', 'lluvia'], ['大雨', 'おおあめ', 'lluvia intensa']],
            '高' => [['高い', 'たかい', 'alto'], ['高校', 'こうこう', 'escuela superior']],
        ] as $glyph => $examples) {
            foreach ($examples as [$surface, $reading, $meaning]) {
                StudyItem::updateOrCreate(['source_key' => "example:$glyph:$surface"], [
                    'type' => 'WORD', 'jlpt_level' => 'N5', 'surface' => $surface,
                    'reading' => $reading, 'meanings_es' => [$meaning],
                ]);
            }
        }
    }

    private function rows(string $file): array
    {
        $lines = file(database_path("seeders/data/$file"), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return array_map(
            fn (string $line): array => array_map('trim', explode('|', $line)),
            array_slice($lines, 1),
        );
    }

    private function meanings(string $meanings): array
    {
        return preg_split('/;\\s*/', $meanings);
    }

    private function romajiVariants(string $romaji): array
    {
        $romaji = strtolower($romaji);
        $kunrei = [
            'shi' => 'si', 'chi' => 'ti', 'tsu' => 'tu', 'fu' => 'hu', 'ji' => 'zi',
            'sha' => 'sya', 'shu' => 'syu', 'sho' => 'syo',
            'ja' => 'zya', 'ju' => 'zyu', 'jo' => 'zyo',
        ];

        return array_values(array_unique(array_filter([$romaji, $kunrei[$romaji] ?? null])));
    }

    private function kanaPosition(string $romaji): array
    {
        $roman = strtolower($romaji);
        $rows = [
            0 => ['a', 'i', 'u', 'e', 'o'], 1 => ['ka', 'ki', 'ku', 'ke', 'ko', 'ga', 'gi', 'gu', 'ge', 'go'],
            2 => ['sa', 'shi', 'su', 'se', 'so', 'za', 'ji', 'zu', 'ze', 'zo'], 3 => ['ta', 'chi', 'tsu', 'te', 'to', 'da', 'dji', 'dzu', 'de', 'do'],
            4 => ['na', 'ni', 'nu', 'ne', 'no'], 5 => ['ha', 'hi', 'fu', 'he', 'ho', 'ba', 'bi', 'bu', 'be', 'bo', 'pa', 'pi', 'pu', 'pe', 'po'],
            6 => ['ma', 'mi', 'mu', 'me', 'mo'], 7 => ['ya', 'yu', 'yo'], 8 => ['ra', 'ri', 'ru', 're', 'ro'], 9 => ['wa', 'wo', 'n'],
        ];
        foreach ($rows as $row => $values) {
            $column = array_search($roman, $values, true);
            if ($column !== false) {
                return ['kana_row' => $row, 'kana_column' => $row === 7 ? [0, 2, 4][$column] : ($row === 9 ? [0, 3, 4][$column] : $column % 5)];
            }
        }

        return ['kana_row' => 0, 'kana_column' => 0];
    }

    private function kanjiData(): array
    {
        return [
            '日' => [['ニチ', 'ジツ'], ['ひ', 'か'], 4], '一' => [['イチ', 'イツ'], ['ひと'], 1], '国' => [['コク'], ['くに'], 8], '人' => [['ジン', 'ニン'], ['ひと'], 2], '年' => [['ネン'], ['とし'], 6], '大' => [['ダイ', 'タイ'], ['おお'], 3], '十' => [['ジュウ'], ['とお'], 2], '二' => [['ニ'], ['ふた'], 2], '本' => [['ホン'], ['もと'], 5], '中' => [['チュウ'], ['なか'], 4],
            '長' => [['チョウ'], ['なが'], 8], '出' => [['シュツ'], ['で'], 5], '三' => [['サン'], ['み'], 3], '時' => [['ジ'], ['とき'], 10], '行' => [['コウ', 'ギョウ'], ['い'], 6], '見' => [['ケン'], ['み'], 7], '月' => [['ゲツ', 'ガツ'], ['つき'], 4], '後' => [['ゴ', 'コウ'], ['あと', 'のち'], 9], '前' => [['ゼン'], ['まえ'], 9], '生' => [['セイ', 'ショウ'], ['い', 'う'], 5],
            '五' => [['ゴ'], ['いつ'], 4], '間' => [['カン', 'ケン'], ['あいだ'], 12], '上' => [['ジョウ'], ['うえ', 'あ'], 3], '東' => [['トウ'], ['ひがし'], 8], '四' => [['シ'], ['よん', 'よ'], 5], '今' => [['コン'], ['いま'], 4], '金' => [['キン'], ['かね'], 8], '九' => [['キュウ', 'ク'], ['ここの'], 2], '入' => [['ニュウ'], ['はい', 'い'], 2], '学' => [['ガク'], ['まな'], 8],
            '高' => [['コウ'], ['たか'], 10], '円' => [['エン'], ['まる'], 4], '子' => [['シ'], ['こ'], 3], '外' => [['ガイ', 'ゲ'], ['そと'], 5], '八' => [['ハチ'], ['や'], 2], '六' => [['ロク'], ['む'], 4], '下' => [['カ', 'ゲ'], ['した', 'さ'], 3], '来' => [['ライ'], ['く'], 7], '気' => [['キ', 'ケ'], [], 6], '小' => [['ショウ'], ['ちい'], 3],
            '七' => [['シチ'], ['なな'], 2], '山' => [['サン'], ['やま'], 3], '話' => [['ワ'], ['はな'], 13], '女' => [['ジョ', 'ニョ'], ['おんな'], 3], '北' => [['ホク'], ['きた'], 5], '午' => [['ゴ'], [], 4], '百' => [['ヒャク'], [], 6], '書' => [['ショ'], ['か'], 10], '先' => [['セン'], ['さき'], 6], '名' => [['メイ', 'ミョウ'], ['な'], 6],
            '川' => [['セン'], ['かわ'], 3], '千' => [['セン'], ['ち'], 3], '水' => [['スイ'], ['みず'], 4], '半' => [['ハン'], ['なか'], 5], '男' => [['ダン', 'ナン'], ['おとこ'], 7], '西' => [['セイ', 'サイ'], ['にし'], 6], '電' => [['デン'], [], 13], '校' => [['コウ'], [], 10], '語' => [['ゴ'], ['かた'], 14], '土' => [['ド', 'ト'], ['つち'], 3],
            '木' => [['モク', 'ボク'], ['き'], 4], '聞' => [['ブン', 'モン'], ['き'], 14], '食' => [['ショク'], ['た'], 9], '車' => [['シャ'], ['くるま'], 7], '何' => [['カ'], ['なに', 'なん'], 7], '南' => [['ナン'], ['みなみ'], 9], '万' => [['マン', 'バン'], [], 3], '毎' => [['マイ'], [], 6], '白' => [['ハク', 'ビャク'], ['しろ'], 5], '天' => [['テン'], ['あめ'], 4],
            '母' => [['ボ'], ['はは'], 5], '火' => [['カ'], ['ひ'], 4], '右' => [['ウ', 'ユウ'], ['みぎ'], 5], '読' => [['ドク', 'トク'], ['よ'], 14], '友' => [['ユウ'], ['とも'], 4], '左' => [['サ'], ['ひだり'], 5], '休' => [['キュウ'], ['やす'], 6], '父' => [['フ'], ['ちち'], 4], '雨' => [['ウ'], ['あめ'], 8],
        ];
    }
}
