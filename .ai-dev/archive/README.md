# Linear ?´å?å·¥å…·??

?™å€‹ç›®?„å??«ä? RentalRadar å°ˆæ???Linear ?´å?å·¥å…·??

## ?? æª”æ?èªªæ?

### **?¸å??´å?å·¥å…·**
- `linear-oauth-integration.cjs` - OAuth èªè??ŒåŸº??API ?ä?
- `linear-issues.cjs` - Issues ç®¡ç?å·¥å…·
- `complete-linear-tasks.cjs` - ?¹é?å®Œæ? Linear ä»»å?
- `project-manager.cjs` - ?¬åœ°å°ˆæ?ç®¡ç?å·¥å…·

### **?™ä»½?Œè??ˆå·¥??*
- `project-status.json` - ?¬åœ°å°ˆæ?ç®¡ç??™ä»½ (Linear ??¥å¤±æ??‚ä½¿??
- `linear-integration.cjs` - ?Šç? API key ?´å?
- `linear-integration.js` - ?Šç? JavaScript ?ˆæœ¬

## ?? ä½¿ç”¨?¹å?

### **1. OAuth èªè?**
```bash
# ?–å??ˆæ? URL
node .ai-dev/linear-integration/linear-oauth-integration.cjs auth

# ä½¿ç”¨?ˆæ?ç¢¼å?å¾?token
node .ai-dev/linear-integration/linear-oauth-integration.cjs token <?ˆæ?ç¢?
```

### **2. ?¥ç? Issues**
```bash
node .ai-dev/linear-integration/linear-issues.cjs list
```

### **3. ?¬åœ°å°ˆæ?ç®¡ç?**
```bash
node .ai-dev/linear-integration/project-manager.cjs status
```

### **4. ?¹é?å®Œæ?ä»»å?**
```bash
node .ai-dev/linear-integration/complete-linear-tasks.cjs complete
```

## ?”§ è¨­å?

### **OAuth è¨­å?**
- Client ID: `7a8573c37786a73a9affd9c04ab46202`
- Client Secret: `fcf427689c053d61a6e22db10cc0663a`
- Redirect URI: `http://localhost:8000/callback`

### **å°ˆæ?è³‡è?**
- Team ID: `40b1bdfd-2caa-4306-9fc4-8c4f2d646cec`
- Project ID: `d7bd332e-2166-4d2f-ba5c-bfd4f01422c5`

## ?? å·¥ä?æµç?

1. **?‹ç™¼??*: ä½¿ç”¨ `project-manager.cjs` è¦å?ä»»å?
2. **?‹ç™¼ä¸?*: ä½¿ç”¨ `linear-issues.cjs` è¿½è¹¤?²åº¦
3. **å®Œæ?å¾?*: ä½¿ç”¨ `complete-linear-tasks.cjs` ?´æ–°?€??

## ?? AI ?˜é??”ä?

?™ä?å·¥å…·?¯æ´å¤?AI ?”ä??‹ç™¼ï¼?
- **Claude**: å°ˆæ??¶æ??Œè???
- **Claude Code**: ?·é??Ÿèƒ½å¯¦ä?
- **Codex**: AI æ¼”ç?æ³•å?è³‡æ?ç§‘å­¸

æ¯å€?AI ?½å¯ä»¥ä½¿?¨é€™ä?å·¥å…·ä¾†å?æ­?Linear ?€?‹ã€?
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
