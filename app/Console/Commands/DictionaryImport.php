<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DictionaryImport extends Command
{
    protected $signature = 'dictionary:import {--fresh : Truncate before importing}';

    protected $description = 'Import the ESDB/SCOWL word list into the dictionary_words table';

    public function handle(): int
    {
        $path = storage_path('dictionary/wordlist.txt');

        if (! file_exists($path)) {
            $this->error("Word list not found at: {$path}");
            $this->error('Please ensure storage/dictionary/wordlist.txt exists.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::table('dictionary_words')->truncate();
            $this->info('Truncated dictionary_words table.');
        }

        $this->info('Importing words...');

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Cannot open {$path}");

            return self::FAILURE;
        }

        $batch = [];
        $total = 0;
        $batchSize = 1000;

        while (($line = fgets($handle)) !== false) {
            $word = trim($line);
            if ($word === '') {
                continue;
            }
            $batch[] = ['word' => $word];
            if (count($batch) >= $batchSize) {
                DB::table('dictionary_words')->insertOrIgnore($batch);
                $total += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('dictionary_words')->insertOrIgnore($batch);
            $total += count($batch);
        }

        fclose($handle);

        $this->info("Import complete. {$total} words processed.");

        return self::SUCCESS;
    }
}
