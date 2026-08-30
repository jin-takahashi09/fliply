<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Word>
 */
class WordFactory extends Factory
{
    protected $model = Word::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'english' => fake()->unique()->word(),
            'japanese' => 'テスト',
            'is_hard' => false,
        ];
    }
}
