<?php

namespace App\Support;

use Normalizer;

class KanaConverter
{
    private const ROMAJI = [
        'kya' => 'きゃ', 'kyu' => 'きゅ', 'kyo' => 'きょ', 'gya' => 'ぎゃ', 'gyu' => 'ぎゅ', 'gyo' => 'ぎょ',
        'sha' => 'しゃ', 'shu' => 'しゅ', 'sho' => 'しょ', 'sya' => 'しゃ', 'syu' => 'しゅ', 'syo' => 'しょ',
        'ja' => 'じゃ', 'ju' => 'じゅ', 'jo' => 'じょ', 'zya' => 'じゃ', 'zyu' => 'じゅ', 'zyo' => 'じょ',
        'cha' => 'ちゃ', 'chu' => 'ちゅ', 'cho' => 'ちょ', 'tya' => 'ちゃ', 'tyu' => 'ちゅ', 'tyo' => 'ちょ',
        'nya' => 'にゃ', 'nyu' => 'にゅ', 'nyo' => 'にょ', 'hya' => 'ひゃ', 'hyu' => 'ひゅ', 'hyo' => 'ひょ',
        'bya' => 'びゃ', 'byu' => 'びゅ', 'byo' => 'びょ', 'pya' => 'ぴゃ', 'pyu' => 'ぴゅ', 'pyo' => 'ぴょ',
        'shi' => 'し', 'si' => 'し', 'chi' => 'ち', 'ti' => 'ち', 'tsu' => 'つ', 'tu' => 'つ',
        'fu' => 'ふ', 'hu' => 'ふ', 'ji' => 'じ', 'zi' => 'じ',
        'ka' => 'か', 'ki' => 'き', 'ku' => 'く', 'ke' => 'け', 'ko' => 'こ',
        'ga' => 'が', 'gi' => 'ぎ', 'gu' => 'ぐ', 'ge' => 'げ', 'go' => 'ご',
        'sa' => 'さ', 'su' => 'す', 'se' => 'せ', 'so' => 'そ',
        'za' => 'ざ', 'zu' => 'ず', 'ze' => 'ぜ', 'zo' => 'ぞ',
        'ta' => 'た', 'te' => 'て', 'to' => 'と', 'da' => 'だ', 'de' => 'で', 'do' => 'ど',
        'na' => 'な', 'ni' => 'に', 'nu' => 'ぬ', 'ne' => 'ね', 'no' => 'の',
        'ha' => 'は', 'hi' => 'ひ', 'he' => 'へ', 'ho' => 'ほ',
        'ba' => 'ば', 'bi' => 'び', 'bu' => 'ぶ', 'be' => 'べ', 'bo' => 'ぼ',
        'pa' => 'ぱ', 'pi' => 'ぴ', 'pu' => 'ぷ', 'pe' => 'ぺ', 'po' => 'ぽ',
        'ma' => 'ま', 'mi' => 'み', 'mu' => 'む', 'me' => 'め', 'mo' => 'も',
        'ya' => 'や', 'yu' => 'ゆ', 'yo' => 'よ', 'ra' => 'ら', 'ri' => 'り', 'ru' => 'る', 're' => 'れ', 'ro' => 'ろ',
        'wa' => 'わ', 'wo' => 'を', 'nn' => 'ん', 'n' => 'ん',
        'a' => 'あ', 'i' => 'い', 'u' => 'う', 'e' => 'え', 'o' => 'お',
    ];

    public static function detect(string $input): ?string
    {
        if (preg_match('/[\x{3400}-\x{9fff}]/u', $input)) {
            return 'KANJI';
        }

        if (preg_match('/^[A-Za-z\\s]+$/', $input)) {
            return 'ROMAJI';
        }

        if (preg_match('/^[ぁ-ゖー\\s]+$/u', $input)) {
            return 'HIRAGANA';
        }

        if (preg_match('/^[ァ-ヺー\\s]+$/u', $input)) {
            return 'KATAKANA';
        }

        return null;
    }

    public static function normalize(string $input, string $script): string
    {
        $input = trim($input);

        return match ($script) {
            'ROMAJI' => self::romajiToHiragana(strtolower($input)),
            'KATAKANA' => mb_convert_kana(self::nfc($input), 'c', 'UTF-8'),
            default => self::nfc($input),
        };
    }

    public static function romajiToHiragana(string $input): string
    {
        $input = str_replace([' ', '-'], '', $input);
        $result = '';

        while ($input !== '') {
            if (preg_match('/^([bcdfghjklmnpqrstvwxyz])\\1/', $input, $match) && $match[1] !== 'n') {
                $result .= 'っ';
                $input = substr($input, 1);

                continue;
            }

            foreach (array_keys(self::ROMAJI) as $romaji) {
                if (str_starts_with($input, $romaji)) {
                    $result .= self::ROMAJI[$romaji];
                    $input = substr($input, strlen($romaji));

                    continue 2;
                }
            }

            return $input;
        }

        return $result;
    }

    private static function nfc(string $input): string
    {
        return class_exists(Normalizer::class) ? Normalizer::normalize($input) : $input;
    }
}
