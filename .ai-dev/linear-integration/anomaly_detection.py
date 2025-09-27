#!/usr/bin/env python3
"""Anomaly detection utilities for Linear Issue DEV-7.

This module implements simple statistical anomaly detectors (Z-score and IQR)
and provides basic evaluation helpers so results can be reported alongside
precision/recall style metrics. It can be used as a standalone CLI utility or
imported in other scripts.
"""
from __future__ import annotations

import argparse
import math
import pathlib
from statistics import mean, stdev
from typing import Iterable, List, Sequence, Tuple


def _parse_numeric_sequence(text: str) -> List[float]:
    """Parse comma or whitespace separated floats from a string."""
    items = [item.strip() for item in text.replace("\n", " ").replace("\t", " ").split(" ") if item.strip()]
    values: List[float] = []
    for piece in ",".join(items).split(","):
        if not piece:
            continue
        values.append(float(piece))
    if not values:
        raise ValueError("No numeric values parsed from input.")
    return values


def _load_values_from_file(path: pathlib.Path) -> List[float]:
    """Load numeric values from a text or CSV file."""
    text = path.read_text().strip()
    if not text:
        raise ValueError(f"File {path} is empty.")
    return _parse_numeric_sequence(text)


def _compute_quartiles(sorted_values: Sequence[float]) -> Tuple[float, float]:
    """Compute Q1 and Q3 for a sorted sequence using Tukey's hinges."""
    n = len(sorted_values)
    if n < 4:
        raise ValueError("At least four data points are required for IQR detection.")
    mid = n // 2
    if n % 2 == 0:
        lower = sorted_values[:mid]
        upper = sorted_values[mid:]
    else:
        lower = sorted_values[:mid]
        upper = sorted_values[mid + 1 :]
    q1 = _median(lower)
    q3 = _median(upper)
    return q1, q3


def _median(values: Sequence[float]) -> float:
    n = len(values)
    if n == 0:
        raise ValueError("Cannot compute median of empty sequence.")
    mid = n // 2
    if n % 2 == 0:
        return (values[mid - 1] + values[mid]) / 2
    return values[mid]


def detect_z_score(values: Sequence[float], threshold: float = 3.0) -> List[Tuple[int, float, float]]:
    """Detect anomalies using a Z-score threshold.

    Returns a list of (index, value, z_score) for points exceeding the threshold.
    """
    if len(values) < 2:
        raise ValueError("At least two data points are required for Z-score detection.")
    mu = mean(values)
    sigma = stdev(values)
    if math.isclose(sigma, 0.0):
        return []
    anomalies: List[Tuple[int, float, float]] = []
    for idx, value in enumerate(values):
        score = (value - mu) / sigma
        if abs(score) > threshold:
            anomalies.append((idx, value, score))
    return anomalies


def detect_iqr(values: Sequence[float], multiplier: float = 1.5) -> List[Tuple[int, float]]:
    """Detect anomalies using the IQR fence method.

    Returns a list of (index, value) outside the Tukey fences.
    """
    if len(values) < 4:
        raise ValueError("At least four data points are required for IQR detection.")
    sorted_values = sorted(values)
    q1, q3 = _compute_quartiles(sorted_values)
    iqr = q3 - q1
    lower_fence = q1 - multiplier * iqr
    upper_fence = q3 + multiplier * iqr
    anomalies: List[Tuple[int, float]] = []
    for idx, value in enumerate(values):
        if value < lower_fence or value > upper_fence:
            anomalies.append((idx, value))
    return anomalies


def evaluate_predictions(anomaly_indices: Iterable[int], labels: Sequence[int]) -> dict:
    """Compute accuracy, precision, recall, F1, and specificity for predictions."""
    predicted = set(anomaly_indices)
    positives = {i for i, label in enumerate(labels) if label}
    tp = len(predicted & positives)
    fp = len(predicted - positives)
    fn = len(positives - predicted)
    tn = len(labels) - tp - fp - fn
    def _div(num: float, denom: float) -> float:
        return num / denom if denom else 0.0
    accuracy = _div(tp + tn, len(labels))
    precision = _div(tp, tp + fp)
    recall = _div(tp, tp + fn)
    specificity = _div(tn, tn + fp)
    f1 = _div(2 * precision * recall, precision + recall) if precision + recall else 0.0
    return {
        "accuracy": accuracy,
        "precision": precision,
        "recall": recall,
        "f1": f1,
        "specificity": specificity,
        "tp": tp,
        "fp": fp,
        "tn": tn,
        "fn": fn,
    }


def _demo_dataset() -> Tuple[List[float], List[int]]:
    """Synthetic dataset with injected anomalies for quick evaluation."""
    baseline = [
        10.4, 9.9, 10.2, 10.8, 9.7, 10.1, 10.5, 9.8, 10.0, 10.3,
        9.6, 10.2, 10.1, 9.9, 10.4, 9.8, 10.2, 10.0, 9.7, 10.3,
        10.1, 9.5, 10.2, 9.8, 10.3, 9.9, 10.0, 9.7, 10.4, 10.2,
    ]
    anomalies = [24.7, 25.3, 26.1]
    labels = [0] * len(baseline)
    labels.extend([1] * len(anomalies))
    values = baseline + anomalies
    return values, labels


def _format_metrics(metrics: dict) -> str:
    keys = ["accuracy", "precision", "recall", "f1", "specificity"]
    parts = [f"{key}={metrics[key]:0.3f}" for key in keys]
    parts.append(f"tp={metrics['tp']}")
    parts.append(f"fp={metrics['fp']}")
    parts.append(f"tn={metrics['tn']}")
    parts.append(f"fn={metrics['fn']}")
    return " | ".join(parts)


def _run_detection(args: argparse.Namespace) -> None:
    if args.demo:
        values, labels = _demo_dataset()
    else:
        if not args.values and not args.file:
            raise SystemExit("Provide --values, --file, or use --demo for the built-in dataset.")
        values = _parse_numeric_sequence(args.values) if args.values else _load_values_from_file(args.file)
        labels = _parse_label_sequence(args.labels) if args.labels else None
    if args.method == "zscore":
        anomalies = detect_z_score(values, args.threshold)
        indices = [idx for idx, _, _ in anomalies]
        print(f"Z-score anomalies (threshold={args.threshold}):")
        for idx, value, score in anomalies:
            print(f"  index={idx:3d} value={value:8.3f} z={score:6.3f}")
    else:
        anomalies = detect_iqr(values, args.multiplier)
        indices = [idx for idx, _ in anomalies]
        print(f"IQR anomalies (multiplier={args.multiplier}):")
        for idx, value in anomalies:
            print(f"  index={idx:3d} value={value:8.3f}")
    if labels is not None:
        metrics = evaluate_predictions(indices, labels)
        print("Metrics:")
        print(f"  {_format_metrics(metrics)}")
    elif args.demo:
        metrics = evaluate_predictions(indices, _demo_dataset()[1])
        print("Metrics (demo labels):")
        print(f"  {_format_metrics(metrics)}")


def _parse_label_sequence(text: str) -> List[int]:
    if text is None:
        return []
    tokens = [tok.strip() for tok in text.replace("\n", " ").split(" ") if tok.strip()]
    labels: List[int] = []
    for piece in ",".join(tokens).split(","):
        if piece:
            value = int(piece)
            if value not in (0, 1):
                raise ValueError("Labels must be 0 or 1.")
            labels.append(value)
    if not labels:
        raise ValueError("No labels parsed from input.")
    return labels


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Simple anomaly detection utility (DEV-7).")
    parser.add_argument("--method", choices=["zscore", "iqr"], default="zscore", help="Detection method to use.")
    parser.add_argument("--threshold", type=float, default=3.0, help="Z-score threshold (for --method zscore).")
    parser.add_argument(
        "--multiplier",
        type=float,
        default=1.5,
        help="IQR multiplier (for --method iqr).",
    )
    parser.add_argument("--values", type=str, help="Comma or whitespace separated numeric values.")
    parser.add_argument("--labels", type=str, help="Optional comma separated anomaly labels (0/1) for metrics.")
    parser.add_argument(
        "--file",
        type=pathlib.Path,
        help="Path to a text or CSV file containing numeric values.",
    )
    parser.add_argument("--demo", action="store_true", help="Run the built-in synthetic demo dataset.")
    return parser


def main() -> None:
    parser = _build_parser()
    args = parser.parse_args()
    _run_detection(args)


if __name__ == "__main__":
    main()