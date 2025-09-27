<?php

namespace Database\Factories;

use App\Models\Prediction;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prediction>
 */
class PredictionFactory extends Factory
{
    protected $model = Prediction::class;

    public function definition(): array
    {
        $price = $this->faker->numberBetween(12000, 55000);
        $confidence = $this->faker->randomFloat(4, 0.55, 0.95);
        $rangeMargin = $price * $this->faker->randomFloat(3, 0.08, 0.18);

        return [
            'property_id' => Property::factory(),
            'model_version' => 'v2.0-hostinger',
            'predicted_price' => $price,
            'confidence' => $confidence,
            'range_min' => max(8000, $price - $rangeMargin),
            'range_max' => $price + $rangeMargin,
            'breakdown' => [
                'base' => 14500,
                'area_component' => $this->faker->numberBetween(3000, 16000),
            ],
            'explanations' => [
                '面積因素' => ['房間數量', '坪數大小'],
            ],
            'metadata' => [
                'generated_at' => now()->toISOString(),
            ],
        ];
    }
}
