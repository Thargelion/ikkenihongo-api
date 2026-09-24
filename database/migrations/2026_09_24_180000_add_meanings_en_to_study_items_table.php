<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_items', function (Blueprint $table) {
            $table->json('meanings_en')->nullable()->after('meanings_es');
        });
    }

    public function down(): void
    {
        Schema::table('study_items', function (Blueprint $table) {
            $table->dropColumn('meanings_en');
        });
    }
};
