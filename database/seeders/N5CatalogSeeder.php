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

    // The key is hex-encoded: accent-insensitive collations (MySQL default) would treat か and が as the same key.
    private function seedKana(string $file, string $script): void
    {
        foreach ($this->rows($file) as [$romaji, $glyph]) {
            StudyItem::updateOrCreate(
                ['source_key' => "kana:$script:".bin2hex($glyph)],
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
        foreach ($this->rows('n5-kanji.tres') as [$glyph, $meanings, $meaningsEs]) {
            [$onyomi, $kunyomi, $strokeCount] = $this->kanjiData()[$glyph];
            StudyItem::updateOrCreate(
                ['source_key' => "kanji:$glyph"],
                [
                    'type' => 'KANJI',
                    'jlpt_level' => 'N5',
                    'glyph' => $glyph,
                    'meanings_es' => $this->meanings($meaningsEs ?: $meanings),
                    'meanings_en' => $this->meanings($meanings),
                    'onyomi' => $onyomi,
                    'kunyomi' => $kunyomi,
                    'stroke_count' => $strokeCount,
                ],
            );
        }
    }

    private function seedWords(): void
    {
        foreach ($this->rows('n5-vocabulary.tres') as [$surface, $reading, $meanings, $meaningsEs]) {
            StudyItem::updateOrCreate(
                ['source_key' => 'word:'.sha1("$surface|$reading|$meanings")],
                [
                    'type' => 'WORD',
                    'jlpt_level' => 'N5',
                    'surface' => $surface ?: $reading,
                    'reading' => $reading,
                    'meanings_es' => $this->meanings($meaningsEs ?: $meanings),
                    'meanings_en' => $this->meanings($meanings),
                ],
            );
        }
    }

    private function seedKanjiExamples(): void
    {
        foreach ([
            '中' => [['中', 'なか', 'dentro', 'Inside'], ['中学校', 'ちゅうがっこう', 'escuela secundaria', 'Junior high school']],
            '何' => [['何', 'なに', 'qué', 'What'], ['何時', 'なんじ', 'qué hora', 'What time']],
            '円' => [['円', 'えん', 'yen', 'Yen'], ['百円', 'ひゃくえん', 'cien yenes', 'One hundred yen']],
            '北' => [['北', 'きた', 'norte', 'North'], ['北口', 'きたぐち', 'salida norte', 'North exit']],
            '千' => [['千', 'せん', 'mil', 'Thousand'], ['千円', 'せんえん', 'mil yenes', 'One thousand yen']],
            '南' => [['南', 'みなみ', 'sur', 'South'], ['南口', 'みなみぐち', 'salida sur', 'South exit']],
            '友' => [['友達', 'ともだち', 'amigo', 'Friend'], ['友人', 'ゆうじん', 'amistad', 'Friendship']],
            '右' => [['右', 'みぎ', 'derecha', 'Right'], ['右手', 'みぎて', 'mano derecha', 'Right hand']],
            '土' => [['土', 'つち', 'tierra', 'Earth'], ['土曜日', 'どようび', 'sábado', 'Saturday']],
            '天' => [['天', 'てん', 'cielo', 'Heaven'], ['天気', 'てんき', 'clima', 'Weather']],
            '山' => [['山', 'やま', 'montaña', 'Mountain'], ['火山', 'かざん', 'volcán', 'Volcano']],
            '川' => [['川', 'かわ', 'río', 'River'], ['川口', 'かわぐち', 'boca del río', 'River mouth']],
            '左' => [['左', 'ひだり', 'izquierda', 'Left'], ['左手', 'ひだりて', 'mano izquierda', 'Left hand']],
            '東' => [['東', 'ひがし', 'este', 'East'], ['東京', 'とうきょう', 'Tokio', 'Tokyo']],
            '校' => [['学校', 'がっこう', 'escuela', 'School'], ['校長', 'こうちょう', 'director escolar', 'School principal']],
            '火' => [['火', 'ひ', 'fuego', 'Fire'], ['火曜日', 'かようび', 'martes', 'Tuesday']],
            '西' => [['西', 'にし', 'oeste', 'West'], ['西口', 'にしぐち', 'salida oeste', 'West exit']],
            '語' => [['日本語', 'にほんご', 'japonés', 'Japanese language'], ['語学', 'ごがく', 'idiomas', 'Language study']],
            '読' => [['読む', 'よむ', 'leer', 'To read'], ['読書', 'どくしょ', 'lectura', 'Reading']],
            '長' => [['長い', 'ながい', 'largo', 'Long'], ['校長', 'こうちょう', 'director escolar', 'School principal']],
            '間' => [['時間', 'じかん', 'tiempo', 'Time'], ['間', 'あいだ', 'intervalo', 'Interval']],
            '雨' => [['雨', 'あめ', 'lluvia', 'Rain'], ['大雨', 'おおあめ', 'lluvia intensa', 'Heavy rain']],
            '高' => [['高い', 'たかい', 'alto', 'Tall; Expensive'], ['高校', 'こうこう', 'escuela superior', 'High school']],
        ] as $glyph => $examples) {
            foreach ($examples as [$surface, $reading, $meaning, $english]) {
                StudyItem::updateOrCreate(['source_key' => "example:$glyph:$surface"], [
                    'type' => 'WORD', 'jlpt_level' => 'N5', 'surface' => $surface,
                    'reading' => $reading, 'meanings_es' => [$meaning], 'meanings_en' => [$english],
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
