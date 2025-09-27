<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;

    protected $fillable = [
        // 基本位置資訊
        'city',                          // 縣市
        'district',                      // 行政區
        'latitude',                      // 緯度 (目前為空，需要地理編碼)
        'longitude',                     // 經度 (目前為空，需要地理編碼)
        'is_geocoded',                   // 是否已地理編碼

        // 租賃核心資訊
        'rental_type',                   // 租賃類型 (住宅/商業/辦公等)
        'total_rent',                    // 總租金
        'rent_per_ping',                 // 每坪租金
        'rent_date',                     // 租賃日期

        // 建物基本資訊
        'building_type',                 // 建物類型
        'area_ping',                     // 面積(坪)
        'building_age',                  // 建物年齡

        // 格局資訊
        'bedrooms',                      // 臥室數
        'living_rooms',                  // 客廳數
        'bathrooms',                     // 衛浴數

        // 設施資訊
        'has_elevator',                  // 是否有電梯
        'has_management_organization',   // 是否有管理組織
        'has_furniture',                 // 是否有傢俱
    ];

    protected function casts(): array
    {
        return [
            'rent_date' => 'date',
            'has_elevator' => 'boolean',
            'has_management_organization' => 'boolean',
            'has_furniture' => 'boolean',
            'is_geocoded' => 'boolean',
            'area_ping' => 'decimal:2',
            'total_rent' => 'decimal:2',
            'rent_per_ping' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * 取得格式化的地址
     */
    public function getFormattedAddressAttribute(): string
    {
        return "{$this->city}{$this->district}";
    }

    /**
     * 取得格式化的格局
     */
    public function getFormattedCompartmentAttribute(): string
    {
        return "{$this->bedrooms}房{$this->living_rooms}廳{$this->bathrooms}衛";
    }

    /**
     * 取得格式化的租金
     */
    public function getFormattedRentAttribute(): string
    {
        return number_format($this->total_rent).'元';
    }

    /**
     * 取得格式化的每坪租金
     */
    public function getFormattedRentPerPingAttribute(): string
    {
        return number_format($this->rent_per_ping).'元/坪';
    }

    /**
     * 取得格式化的面積
     */
    public function getFormattedAreaAttribute(): string
    {
        return $this->area_ping.'坪';
    }

    /**
     * 取得格式化的建物年齡
     */
    public function getFormattedBuildingAgeAttribute(): string
    {
        return $this->building_age ? $this->building_age.'年' : '未知';
    }

    /**
     * 查詢已地理編碼的記錄
     */
    public function scopeGeocoded($query)
    {
        return $query->where('is_geocoded', true);
    }

    /**
     * 查詢指定範圍內的記錄
     */
    public function scopeWithinBounds($query, float $northLat, float $southLat, float $eastLng, float $westLng)
    {
        return $query->whereBetween('latitude', [$southLat, $northLat])
            ->whereBetween('longitude', [$westLng, $eastLng]);
    }

    /**
     * 查詢指定縣市的記錄
     */
    public function scopeByCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    /**
     * 查詢指定行政區的記錄
     */
    public function scopeByDistrict($query, string $district)
    {
        return $query->where('district', $district);
    }

    /**
     * 查詢指定租賃類型的記錄
     */
    public function scopeByRentalType($query, string $rentalType)
    {
        return $query->where('rental_type', $rentalType);
    }

    /**
     * 查詢指定建物類型的記錄
     */
    public function scopeByBuildingType($query, string $buildingType)
    {
        return $query->where('building_type', $buildingType);
    }

    /**
     * 查詢指定租金範圍的記錄
     */
    public function scopeRentBetween($query, float $minRent, float $maxRent)
    {
        return $query->whereBetween('total_rent', [$minRent, $maxRent]);
    }

    /**
     * 查詢指定每坪租金範圍的記錄
     */
    public function scopeRentPerPingBetween($query, float $minRentPerPing, float $maxRentPerPing)
    {
        return $query->whereBetween('rent_per_ping', [$minRentPerPing, $maxRentPerPing]);
    }

    /**
     * 查詢指定面積範圍的記錄
     */
    public function scopeAreaBetween($query, float $minArea, float $maxArea)
    {
        return $query->whereBetween('area_ping', [$minArea, $maxArea]);
    }

    /**
     * 查詢指定租賃日期的記錄
     */
    public function scopeRentDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('rent_date', [$startDate, $endDate]);
    }

    /**
     * 查詢有電梯的記錄
     */
    public function scopeWithElevator($query)
    {
        return $query->where('has_elevator', true);
    }

    /**
     * 查詢有管理組織的記錄
     */
    public function scopeWithManagement($query)
    {
        return $query->where('has_management_organization', true);
    }

    /**
     * 查詢有附傢俱的記錄
     */
    public function scopeWithFurniture($query)
    {
        return $query->where('has_furniture', true);
    }
}
