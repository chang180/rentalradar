# 🗺️ 地圖系統 API 規格

## 📋 API 概述

RentalRadar 地圖系統的 RESTful API 規格，提供租屋資料查詢、AI 分析、熱力圖等功能。

## 🔗 基礎 URL

```
開發環境: https://rentalradar.test/api
生產環境: https://rentalradar.com/api
```

## 🔐 認證

所有 API 需要 Bearer Token 認證：

```http
Authorization: Bearer {token}
```

## 📊 API 端點

### **1. 租屋資料查詢**

#### **GET /api/map/rentals**

查詢指定區域的租屋資料。

**請求參數:**
```json
{
  "bounds": {
    "north": 25.1,
    "south": 24.9,
    "east": 121.6,
    "west": 121.4
  },
  "filters": {
    "price_min": 10000,
    "price_max": 50000,
    "room_type": "1房1廳",
    "area_min": 20,
    "area_max": 50
  },
  "options": {
    "clustering": true,
    "heatmap": false,
    "limit": 1000
  }
}
```

**回應格式:**
```json
{
  "success": true,
  "data": {
    "rentals": [
      {
        "id": 1,
        "title": "台北市信義區套房",
        "price": 25000,
        "area": 15,
        "room_type": "1房1廳",
        "location": {
          "lat": 25.0330,
          "lng": 121.5654,
          "address": "台北市信義區信義路五段7號"
        },
        "features": ["電梯", "冷氣", "網路"],
        "images": ["image1.jpg", "image2.jpg"],
        "created_at": "2025-09-27T10:00:00Z"
      }
    ],
    "clusters": [
      {
        "id": "cluster_1",
        "center": {
          "lat": 25.0330,
          "lng": 121.5654
        },
        "count": 15,
        "bounds": {
          "north": 25.0340,
          "south": 25.0320,
          "east": 121.5664,
          "west": 121.5644
        }
      }
    ],
    "heatmap_data": [
      {
        "lat": 25.0330,
        "lng": 121.5654,
        "weight": 0.8
      }
    ],
    "statistics": {
      "total_count": 150,
      "average_price": 28000,
      "price_range": {
        "min": 15000,
        "max": 50000
      },
      "area_range": {
        "min": 10,
        "max": 60
      }
    }
  },
  "meta": {
    "page": 1,
    "per_page": 100,
    "total": 150,
    "last_page": 2
  }
}
```

### **2. 熱力圖資料**

#### **GET /api/map/heatmap**

取得價格熱力圖資料。

**請求參數:**
```json
{
  "bounds": {
    "north": 25.1,
    "south": 24.9,
    "east": 121.6,
    "west": 121.4
  },
  "type": "price_density",
  "resolution": "high"
}
```

**回應格式:**
```json
{
  "success": true,
  "data": {
    "heatmap_points": [
      {
        "lat": 25.0330,
        "lng": 121.5654,
        "weight": 0.8,
        "price_range": "20000-30000"
      }
    ],
    "color_scale": {
      "min": 0.1,
      "max": 1.0,
      "colors": ["#00ff00", "#ffff00", "#ff0000"]
    },
    "statistics": {
      "total_points": 1000,
      "density_range": {
        "min": 0.1,
        "max": 1.0
      }
    }
  }
}
```

### **3. AI 分析**

#### **POST /api/ai/analyze**

執行 AI 分析任務。

**請求參數:**
```json
{
  "type": "price_prediction",
  "data": {
    "location": {
      "lat": 25.0330,
      "lng": 121.5654
    },
    "features": {
      "area": 25,
      "room_type": "1房1廳",
      "floor": 5,
      "age": 10
    }
  },
  "parameters": {
    "model_version": "v2.1",
    "confidence_threshold": 0.8
  }
}
```

**回應格式:**
```json
{
  "success": true,
  "data": {
    "predictions": {
      "price": 28000,
      "confidence": 0.85,
      "range": {
        "min": 25000,
        "max": 31000
      }
    },
    "model_info": {
      "version": "v2.1",
      "accuracy": 0.92,
      "last_trained": "2025-09-20T00:00:00Z"
    },
    "performance_metrics": {
      "processing_time": 0.15,
      "memory_usage": "45MB"
    }
  }
}
```

### **4. 聚合資料**

#### **GET /api/map/clusters**

取得智慧聚合資料。

**請求參數:**
```json
{
  "bounds": {
    "north": 25.1,
    "south": 24.9,
    "east": 121.6,
    "west": 121.4
  },
  "zoom_level": 12,
  "algorithm": "kmeans"
}
```

**回應格式:**
```json
{
  "success": true,
  "data": {
    "clusters": [
      {
        "id": "cluster_1",
        "center": {
          "lat": 25.0330,
          "lng": 121.5654
        },
        "count": 15,
        "bounds": {
          "north": 25.0340,
          "south": 25.0320,
          "east": 121.5664,
          "west": 121.5644
        },
        "statistics": {
          "average_price": 28000,
          "price_range": {
            "min": 20000,
            "max": 35000
          }
        }
      }
    ],
    "algorithm_info": {
      "type": "kmeans",
      "parameters": {
        "k": 10,
        "iterations": 100
      },
      "performance": {
        "processing_time": 0.25,
        "accuracy": 0.88
      }
    }
  }
}
```

## 🚨 錯誤處理

### **錯誤回應格式**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid bounds parameter",
    "details": {
      "field": "bounds.north",
      "value": "invalid",
      "expected": "number"
    }
  },
  "meta": {
    "timestamp": "2025-09-27T10:00:00Z",
    "request_id": "req_123456"
  }
}
```

### **常見錯誤碼**
- `VALIDATION_ERROR`: 參數驗證錯誤
- `AUTHENTICATION_ERROR`: 認證失敗
- `RATE_LIMIT_EXCEEDED`: 請求頻率超限
- `AI_SERVICE_ERROR`: AI 服務錯誤
- `DATABASE_ERROR`: 資料庫錯誤

## 📊 效能指標

### **回應時間目標**
- 租屋資料查詢: < 500ms
- 熱力圖資料: < 800ms
- AI 分析: < 1000ms
- 聚合資料: < 600ms

### **快取策略**
- 租屋資料: 5分鐘
- 熱力圖資料: 10分鐘
- AI 分析結果: 1小時
- 聚合資料: 15分鐘

## 🔧 開發指南

### **Claude Code 開發重點**
1. 建立 `MapController` 類別
2. 實作資料查詢和篩選邏輯
3. 整合 AI 服務呼叫
4. 實作快取機制
5. 建立 API 測試

### **Codex 整合重點**
1. 開發 Python AI 演算法
2. 建立 JavaScript 前端演算法
3. 實作效能優化
4. 建立監控機制

### **測試要求**
- 單元測試覆蓋率 > 80%
- 整合測試通過率 100%
- 效能測試達標
- 錯誤處理測試

---

**📋 API 設計者**: Claude (架構師)  
**📅 建立日期**: 2025-09-27  
**🔄 版本**: v1.0  
**📋 狀態**: 規格完成，等待開發團隊實作
