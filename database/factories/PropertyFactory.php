<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = ['台北市', '新北市', '桃園市', '台中市', '台南市', '高雄市'];
        $districts = ['松山區', '信義區', '大安區', '中山區', '中正區', '大同區', '萬華區', '文山區', '南港區', '內湖區', '士林區', '北投區'];
        $buildingTypes = ['住宅大樓', '華廈', '公寓', '透天厝', '套房'];
        $rentalTypes = ['整層住家', '分租套房', '獨立套房', '雅房', '店面', '辦公室'];

        $city = fake()->randomElement($cities);
        $district = fake()->randomElement($districts);
        $areaPing = fake()->randomFloat(2, 5, 100); // 面積(坪)
        $rentPerPing = fake()->randomFloat(2, 800, 2500); // 每坪租金
        $totalRent = $areaPing * $rentPerPing; // 總租金

        $latitude = fake()->latitude(25.001, 25.199);
        $longitude = fake()->longitude(121.450, 121.650);

        return [
            // 資料驗證資訊
            'serial_number' => fake()->unique()->numerify('GOV-####-####-####'),

            // 基本位置資訊
            'city' => $city,
            'district' => $district,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_geocoded' => fake()->boolean(80),

            // 租賃核心資訊
            'rental_type' => fake()->randomElement($rentalTypes),
            'total_rent' => $totalRent,
            'rent_per_ping' => $rentPerPing,
            'rent_date' => fake()->dateTimeBetween('-2 years', 'now'),

            // 建物基本資訊
            'building_type' => fake()->randomElement($buildingTypes),
            'area_ping' => $areaPing,
            'building_age' => fake()->numberBetween(0, 50),

            // 格局資訊
            'bedrooms' => fake()->numberBetween(0, 4),
            'living_rooms' => fake()->numberBetween(0, 2),
            'bathrooms' => fake()->numberBetween(1, 3),

            // 設施資訊
            'has_elevator' => fake()->boolean(60),
            'has_management_organization' => fake()->boolean(70),
            'has_furniture' => fake()->boolean(40),
        ];
    }
}
