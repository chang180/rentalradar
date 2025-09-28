# Deployment Documentation

- [Hostinger 模型部署指南](./hostinger-model-deployment.md)

## Recent Updates (2025-01-XX)

### Map System Optimization
- **Fixed map rental count display**: Improved from 3,638 to 4,363 properties with coordinates
- **Implemented Redis caching**: Added Redis caching for geographic data with 4-5x performance improvement
- **Enhanced coordinate coverage**: Added missing district coordinates (e.g., Kaohsiung Cieding District)
- **Fixed city name mapping**: Resolved character encoding issues between traditional and simplified Chinese characters

### Technical Improvements
- **Redis Cache Implementation**: 
  - Dual-layer caching strategy for individual coordinates and full dataset
  - 24-hour cache duration using Redis database 1
  - Cache key format: `rentalradar_database_rentalradar_cache_geo_coordinates:{city}:{district}`
- **Map Functionality**: 
  - Fixed property.area.toFixed errors
  - Increased marker sizes for better visibility
  - Resolved initial load marker display issues
  - Optimized data loading logic for aggregated data on initial load