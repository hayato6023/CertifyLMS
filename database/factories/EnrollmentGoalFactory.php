<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentGoal>
 */
class EnrollmentGoalFactory extends Factory
{
    protected $model = EnrollmentGoal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'title' => fake()->randomElement([
                '過去問 5 年分を解き終える',
                '苦手分野を week 単位で潰す',
                '模試で 80% を安定して取る',
                '毎日 1 時間の学習を継続する',
            ]),
            'description' => fake()->optional()->sentence(),
            'target_date' => fake()->optional()->dateTimeBetween('now', '+3 months')?->format('Y-m-d'),
            'achieved_at' => null,
        ];
    }

    public function achieved(): static
    {
        return $this->state(fn () => ['achieved_at' => now()]);
    }

    public function unachieved(): static
    {
        return $this->state(fn () => ['achieved_at' => null]);
    }
}
