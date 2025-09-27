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
        $districts = ['松山區', '信義區', '大安區', '中山區', '中正區', '大同區', '萬華區', '文山區', '南港區', '內湖區', '士林區', '北投區'];
        $buildingTypes = ['住宅大樓', '華廈', '公寓', '透天厝', '套房'];
        $mainUses = ['住家用', '商業用', '工業用', '其他'];
        $buildingMaterials = ['鋼筋混凝土造', '鋼骨造', '磚造', '其他'];
        $patterns = ['1房1廳1衛', '2房1廳1衛', '2房2廳1衛', '3房2廳2衛', '4房2廳2衛'];

        $district = fake()->randomElement($districts);
        $totalFloorArea = fake()->randomFloat(2, 10, 100);
        $rentPerMonth = fake()->randomFloat(2, 300, 2000);

        $latitude = fake()->latitude(25.001, 25.199);
        $longitude = fake()->longitude(121.450, 121.650);

        $villageNames = ['中正里', '信義里', '和平里', '光復里', '復興里', '民生里', '忠孝里', '仁愛里'];
        $roadNames = ['忠孝東路', '信義路', '仁愛路', '和平東路', '羅斯福路', '中山北路', '敦化南路', '復興南路'];

        return [
            'district' => $district,
            'village' => fake()->randomElement($villageNames),
            'road' => fake()->randomElement($roadNames) . fake()->numberBetween(1, 7) . '段',
            'land_section' => fake()->optional()->word(),
            'land_subsection' => fake()->optional()->word(),
            'land_number' => fake()->optional()->numerify('####'),
            'building_type' => fake()->randomElement($buildingTypes),
            'total_floor_area' => $totalFloorArea,
            'main_use' => fake()->randomElement($mainUses),
            'main_building_materials' => fake()->randomElement($buildingMaterials),
            'construction_completion_year' => fake()->year(1970),
            'total_floors' => fake()->numberBetween(1, 30),
            'compartment_pattern' => fake()->randomElement($patterns),
            'has_management_organization' => fake()->boolean(),
            'rent_per_month' => $rentPerMonth,
            'total_rent' => $totalFloorArea * $rentPerMonth,
            'rent_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'rental_period' => fake()->randomElement(['1年', '2年', '3年']),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'full_address' => "台北市{$district}" . fake()->streetAddress(),
            'is_geocoded' => fake()->boolean(80),
            'data_source' => 'government',
            'is_processed' => fake()->boolean(60),
            'processing_notes' => fake()->optional()->randomElements(['AI處理完成', '地址驗證', '價格異常檢測'], fake()->numberBetween(0, 2)),
        ];
    }
}
