# Linear ?��?工具??

?�個目?��??��? RentalRadar 專�???Linear ?��?工具??

## ?? 檔�?說�?

### **?��??��?工具**
- `linear-oauth-integration.cjs` - OAuth 認�??�基??API ?��?
- `linear-issues.cjs` - Issues 管�?工具
- `complete-linear-tasks.cjs` - ?��?完�? Linear 任�?
- `project-manager.cjs` - ?�地專�?管�?工具

### **?�份?��??�工??*
- `project-status.json` - ?�地專�?管�??�份 (Linear ??��失�??�使??
- `linear-integration.cjs` - ?��? API key ?��?
- `linear-integration.js` - ?��? JavaScript ?�本

## ?? 使用?��?

### **1. OAuth 認�?**
```bash
# ?��??��? URL
node .ai-dev/linear-integration/linear-oauth-integration.cjs auth

# 使用?��?碼�?�?token
node .ai-dev/linear-integration/linear-oauth-integration.cjs token <?��?�?
```

### **2. ?��? Issues**
```bash
node .ai-dev/linear-integration/linear-issues.cjs list
```

### **3. ?�地專�?管�?**
```bash
node .ai-dev/linear-integration/project-manager.cjs status
```

### **4. ?��?完�?任�?**
```bash
node .ai-dev/linear-integration/complete-linear-tasks.cjs complete
```

## ?�� 設�?

### **OAuth 設�?**
- Client ID: `7a8573c37786a73a9affd9c04ab46202`
- Client Secret: `fcf427689c053d61a6e22db10cc0663a`
- Redirect URI: `http://localhost:8000/callback`

### **專�?資�?**
- Team ID: `40b1bdfd-2caa-4306-9fc4-8c4f2d646cec`
- Project ID: `d7bd332e-2166-4d2f-ba5c-bfd4f01422c5`

## ?? 工�?流�?

1. **?�發??*: 使用 `project-manager.cjs` 規�?任�?
2. **?�發�?*: 使用 `linear-issues.cjs` 追蹤?�度
3. **完�?�?*: 使用 `complete-linear-tasks.cjs` ?�新?�??

## ?? AI ?��??��?

?��?工具?�援�?AI ?��??�發�?
- **Claude**: 專�??��??��???
- **Claude Code**: ?��??�能實�?
- **Codex**: AI 演�?法�?資�?科學

每�?AI ?�可以使?�這�?工具來�?�?Linear ?�?��?
## DEV-7 Anomaly Detection Utility

### Files
- `.ai-dev/linear-integration/anomaly_detection.py` - Python CLI module with Z-score and IQR detectors plus evaluation helpers.

### Quick Start
- Run the synthetic demo: `python .ai-dev/linear-integration/anomaly_detection.py --demo`
- Custom Z-score example: `python .ai-dev/linear-integration/anomaly_detection.py --method zscore --threshold 2.5 --values "10,10,10,28" --labels "0,0,0,1"`
- IQR example reading a file: `python .ai-dev/linear-integration/anomaly_detection.py --method iqr --file data/sample.csv --labels "0,0,0,1"`

### Demo Performance (synthetic dataset)
- Z-score (threshold 2.5): accuracy 1.000, precision 1.000, recall 1.000, f1 1.000, specificity 1.000
- IQR (multiplier 1.5): accuracy 1.000, precision 1.000, recall 1.000, f1 1.000, specificity 1.000

### Implementation Notes
- Z-score detector flags values where `abs(value - mean) / std` exceeds the threshold.
- IQR detector uses Tukey fences `Q1 +/- multiplier * IQR` to identify outliers.
- Metrics include accuracy, precision, recall, f1, specificity, and the TP/FP/TN/FN counts.
- Exposed helpers: `detect_z_score`, `detect_iqr`, `evaluate_predictions`.
