<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Recommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    public function definition(): array
    {
        $titles = ['投資首選', '交通便利優質', '學區附近推薦', '生活機能完善'];
        $summaries = [
            '位於台北市精華地段的優質物件',
            '鄰近捷運站，交通便利，適合上班族',
            '學區優質，適合有小孩的家庭',
            '生活機能完善，購物便利',
        ];

        return [
            'property_id' => Property::factory(),
            'type' => $this->faker->randomElement(['investment', 'family', 'transport', 'lifestyle']),
            'title' => $this->faker->randomElement($titles),
            'summary' => $this->faker->randomElement($summaries),
            'reasons' => [
                '主要優點' => $this->faker->randomElements([
                    '交通便利，鄰近捷運站',
                    '生活機能完善，購物方便',
                    '學區優質，適合家庭',
                    '價格合理，投資價值高',
                ], 2),
            ],
            'metadata' => [
                'generated_at' => now()->toISOString(),
            ],
            'score' => $this->faker->randomFloat(2, 70, 95),
        ];
    }
}
