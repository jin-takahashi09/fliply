<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_words', function (Blueprint $table) {
            $table->id();
            $table->string('word')->unique();
        });

        // Case-insensitive prefix search index via a generated lowercase column
        DB::statement('CREATE INDEX dictionary_words_word_lower_idx ON dictionary_words (lower(word))');
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_words');
    }
};
