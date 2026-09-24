<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kana keys were built from the glyph itself ("kana:HIRAGANA:か"). MySQL's accent-insensitive
 * collations treat か and が as equal, so seeding the voiced kana overwrote the base kana rows.
 * The surviving key still names the original glyph, so it is rewritten to its hex code points
 * (ASCII, so no collation can merge two kana). Re-run N5CatalogSeeder afterwards to restore
 * the glyph, romaji and grid position of each row and to add the missing voiced kana.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('study_items')->where('type', 'KANA')->orderBy('id')->each(function (object $row): void {
            [$prefix, $script, $glyph] = array_pad(explode(':', $row->source_key, 3), 3, null);

            if ($prefix !== 'kana' || $glyph === null || preg_match('/^[0-9a-f]+$/', $glyph)) {
                return;
            }

            DB::table('study_items')->where('id', $row->id)->update([
                'source_key' => "kana:$script:".bin2hex($glyph),
            ]);
        });
    }

    public function down(): void
    {
        // The readable keys cannot be told apart safely on collations that fold kana; keep the new ones.
    }
};
