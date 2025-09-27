#!/usr/bin/env python3
"""Anomaly detection and smart marker tooling for Linear Issues DEV-7 and DEV-12.

This module implements statistical anomaly detectors (Z-score and IQR) and a
lightweight geospatial marker clustering algorithm with value-aware summaries.
It can be used as a standalone CLI utility or imported into other scripts.
"""
from __future__ import annotations

import argparse
import math
import pathlib
import re
from collections import deque
from dataclasses import dataclass
from statistics import mean, stdev
from typing import Dict, Iterable, List, Optional, Sequence, Set, Tuple


EARTH_RADIUS_KM = 6371.0088


@dataclass(slots=True)
class MarkerPoint:
    """Geospatial marker with an optional numeric value (e.g. price)."""

    id: int
    lat: float
    lon: float
    value: Optional[float] = None


@dataclass(slots=True)
class ClusterSummary:
    """Aggregated information about a smart marker cluster."""

    id: int
    size: int
    member_ids: List[int]
    centroid_lat: float
    centroid_lon: float
    radius_km: float
    bounding_box: Tuple[float, float, float, float]
    density: float
    average_value: Optional[float]
    median_value: Optional[float]
    min_value: Optional[float]
    max_value: Optional[float]
    value_std: Optional[float]
    value_outliers: List[Tuple[int, float, float]]


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


def _radius_for_zoom(
    base_radius_km: float,
    zoom_level: Optional[int],
    reference_zoom: int,
    decay: float,
) -> float:
    """Scale the clustering radius based on the current map zoom level."""

    if base_radius_km <= 0:
        raise ValueError("base_radius_km must be positive.")
    if zoom_level is None:
        return base_radius_km
    if decay <= 0:
        raise ValueError("zoom_decay must be positive.")
    if math.isclose(decay, 1.0):
        return base_radius_km
    delta = zoom_level - reference_zoom
    if delta == 0:
        return base_radius_km
    if delta > 0:
        factor = decay ** delta
    else:
        factor = (1 / decay) ** (-delta)
    return base_radius_km * factor


def _make_projector(points: Sequence[MarkerPoint]):
    """Return an equirectangular projection anchored at the average latitude."""

    if not points:
        raise ValueError("Cannot build projector for an empty point sequence.")
    average_lat = mean(point.lat for point in points)
    cos_lat = math.cos(math.radians(average_lat))
    cos_lat = max(cos_lat, 1e-6)

    def project(lat: float, lon: float) -> Tuple[float, float]:
        x = EARTH_RADIUS_KM * math.radians(lon) * cos_lat
        y = EARTH_RADIUS_KM * math.radians(lat)
        return x, y

    return project


def _grid_key(x: float, y: float, cell_size: float) -> Tuple[int, int]:
    """Bucket projected coordinates into a grid cell."""

    if cell_size <= 0:
        raise ValueError("cell_size must be positive.")
    return math.floor(x / cell_size), math.floor(y / cell_size)


def _haversine_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    """Compute the Haversine distance between two latitude/longitude points."""

    lat1_rad = math.radians(lat1)
    lat2_rad = math.radians(lat2)
    dlat = lat2_rad - lat1_rad
    dlon = math.radians(lon2 - lon1)
    a = math.sin(dlat / 2) ** 2 + math.cos(lat1_rad) * math.cos(lat2_rad) * math.sin(dlon / 2) ** 2
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return EARTH_RADIUS_KM * c


def smart_marker_clusters(
    points: Sequence[MarkerPoint],
    base_radius_km: float = 0.6,
    zoom_level: Optional[int] = None,
    min_cluster_size: int = 3,
    reference_zoom: int = 12,
    zoom_decay: float = 0.6,
    value_z_threshold: Optional[float] = 2.5,
    value_min_points: int = 3,
) -> Tuple[List[ClusterSummary], List[MarkerPoint]]:
    """Cluster map markers with a lightweight density-inspired algorithm."""

    if base_radius_km <= 0:
        raise ValueError("base_radius_km must be positive.")
    if min_cluster_size < 1:
        raise ValueError("min_cluster_size must be at least one.")
    if value_min_points < 2:
        raise ValueError("value_min_points must be at least two.")
    if not points:
        return [], []

    effective_radius = _radius_for_zoom(base_radius_km, zoom_level, reference_zoom, zoom_decay)
    projector = _make_projector(points)
    projected = [projector(point.lat, point.lon) for point in points]

    grid: Dict[Tuple[int, int], List[int]] = {}
    for idx, (x, y) in enumerate(projected):
        key = _grid_key(x, y, effective_radius)
        grid.setdefault(key, []).append(idx)

    def region_query(index: int) -> List[int]:
        x, y = projected[index]
        key_x, key_y = _grid_key(x, y, effective_radius)
        candidates: Set[int] = set()
        for gx in range(key_x - 1, key_x + 2):
            for gy in range(key_y - 1, key_y + 2):
                candidates.update(grid.get((gx, gy), ()))
        candidates.discard(index)
        neighbors: List[int] = []
        for candidate in candidates:
            cx, cy = projected[candidate]
            if math.hypot(cx - x, cy - y) <= effective_radius:
                neighbors.append(candidate)
        neighbors.sort()
        return neighbors

    visited = [False] * len(points)
    cluster_ids = [-1] * len(points)
    clusters: List[List[int]] = []

    for idx in range(len(points)):
        if visited[idx]:
            continue
        visited[idx] = True
        neighbors = region_query(idx)
        if len(neighbors) + 1 < min_cluster_size:
            continue
        cluster_members: List[int] = [idx]
        cluster_index = len(clusters)
        cluster_ids[idx] = cluster_index
        queue: deque[int] = deque(neighbors)
        queue_tracker: Set[int] = set(neighbors)
        while queue:
            neighbor_idx = queue.popleft()
            queue_tracker.discard(neighbor_idx)
            if not visited[neighbor_idx]:
                visited[neighbor_idx] = True
                neighbor_neighbors = region_query(neighbor_idx)
                if len(neighbor_neighbors) + 1 >= min_cluster_size:
                    for candidate in neighbor_neighbors:
                        if candidate not in queue_tracker:
                            queue.append(candidate)
                            queue_tracker.add(candidate)
            if cluster_ids[neighbor_idx] == -1:
                cluster_ids[neighbor_idx] = cluster_index
                cluster_members.append(neighbor_idx)
        clusters.append(cluster_members)

    cluster_summaries = [
        _summarize_cluster(
            cluster_id,
            member_indices,
            points,
            value_z_threshold,
            value_min_points,
        )
        for cluster_id, member_indices in enumerate(clusters)
    ]
    noise_points = [points[i] for i, cluster_id in enumerate(cluster_ids) if cluster_id == -1]
    return cluster_summaries, noise_points


def _summarize_cluster(
    cluster_id: int,
    member_indices: Sequence[int],
    points: Sequence[MarkerPoint],
    value_z_threshold: Optional[float],
    value_min_points: int,
) -> ClusterSummary:
    members = [points[i] for i in member_indices]
    size = len(members)
    centroid_lat = sum(point.lat for point in members) / size
    centroid_lon = sum(point.lon for point in members) / size
    min_lat = min(point.lat for point in members)
    max_lat = max(point.lat for point in members)
    min_lon = min(point.lon for point in members)
    max_lon = max(point.lon for point in members)
    radius = 0.0
    for member in members:
        radius = max(radius, _haversine_km(centroid_lat, centroid_lon, member.lat, member.lon))
    density = float("inf") if math.isclose(radius, 0.0) else size / (math.pi * radius * radius)

    value_pairs = [(member.id, member.value) for member in members if member.value is not None]
    if value_pairs:
        values_only = [value for _, value in value_pairs]
        average_value = sum(values_only) / len(values_only)
        sorted_values = sorted(values_only)
        median_value = _median(sorted_values)
        min_value = sorted_values[0]
        max_value = sorted_values[-1]
        value_std = stdev(values_only) if len(values_only) > 1 else None
        value_outliers: List[Tuple[int, float, float]] = []
        if (
            value_z_threshold is not None
            and value_z_threshold > 0
            and len(values_only) >= value_min_points
        ):
            anomalies = detect_z_score(values_only, threshold=value_z_threshold)
            for local_index, value, score in anomalies:
                point_id = value_pairs[local_index][0]
                value_outliers.append((point_id, value, score))
    else:
        average_value = None
        median_value = None
        min_value = None
        max_value = None
        value_std = None
        value_outliers = []

    return ClusterSummary(
        id=cluster_id,
        size=size,
        member_ids=sorted(member.id for member in members),
        centroid_lat=centroid_lat,
        centroid_lon=centroid_lon,
        radius_km=radius,
        bounding_box=(min_lat, min_lon, max_lat, max_lon),
        density=density,
        average_value=average_value,
        median_value=median_value,
        min_value=min_value,
        max_value=max_value,
        value_std=value_std,
        value_outliers=value_outliers,
    )


def _parse_marker_entries(text: str) -> List[Tuple[float, float, Optional[float]]]:
    """Parse lat/lon/(value) triples from text input."""

    entries: List[Tuple[float, float, Optional[float]]] = []
    normalized = text.replace("\r\n", "\n").replace("\r", "\n")
    normalized = normalized.replace(";", "\n").replace("|", "\n")
    for raw_line in normalized.splitlines():
        line = raw_line.split("#", 1)[0].strip()
        if not line:
            continue
        parts = [piece for piece in re.split(r"[\s,]+", line) if piece]
        if len(parts) < 2:
            raise ValueError(f"Cannot parse coordinate line: '{raw_line}'.")
        lat = float(parts[0])
        lon = float(parts[1])
        value = float(parts[2]) if len(parts) > 2 else None
        entries.append((lat, lon, value))
    return entries


def _load_marker_points(coords: Optional[str], coord_file: Optional[pathlib.Path]) -> List[MarkerPoint]:
    """Collect marker points from inline coordinates and optional file."""

    entries: List[Tuple[float, float, Optional[float]]] = []
    if coords:
        entries.extend(_parse_marker_entries(coords))
    if coord_file:
        if not coord_file.exists():
            raise FileNotFoundError(coord_file)
        file_text = coord_file.read_text().strip()
        if not file_text:
            raise ValueError(f"File {coord_file} is empty.")
        entries.extend(_parse_marker_entries(file_text))
    if not entries:
        raise ValueError("No coordinate data provided. Use --coords, --coord-file, or --demo.")
    points = [MarkerPoint(id=idx, lat=lat, lon=lon, value=value) for idx, (lat, lon, value) in enumerate(entries)]
    return points


def _demo_cluster_dataset() -> List[MarkerPoint]:
    """Synthetic map markers for showcasing clustering behaviour."""

    entries = [
        (25.0331, 121.5652, 19800.0),
        (25.0333, 121.5655, 20100.0),
        (25.0330, 121.5650, 20500.0),
        (25.0340, 121.5656, 19950.0),
        (25.0327, 121.5652, 20200.0),
        (25.0495, 121.5201, 16800.0),
        (25.0498, 121.5206, 17150.0),
        (25.0502, 121.5204, 16920.0),
        (25.0500, 121.5198, 16750.0),
        (25.0505, 121.5209, 22000.0),
        (25.0701, 121.5580, 32000.0),
        (25.0705, 121.5585, 31800.0),
        (25.0702, 121.5590, 32200.0),
        (25.0699, 121.5582, 31750.0),
        (25.0707, 121.5578, 32500.0),
        (25.0611, 121.5901, 19500.0),
        (25.0822, 121.5400, 24000.0),
    ]
    return [MarkerPoint(id=idx, lat=lat, lon=lon, value=value) for idx, (lat, lon, value) in enumerate(entries)]


def _print_cluster_report(
    clusters: Sequence[ClusterSummary],
    noise: Sequence[MarkerPoint],
    z_threshold: Optional[float],
    show_noise: bool,
) -> None:
    """Render clustering results to stdout for quick inspection."""

    total_points = sum(cluster.size for cluster in clusters) + len(noise)
    print(
        f"Smart marker clustering analysed {total_points} markers -> {len(clusters)} clusters ({len(noise)} noise)."
    )
    if clusters:
        average_size = sum(cluster.size for cluster in clusters) / len(clusters)
        largest = max(clusters, key=lambda cluster: cluster.size)
        print(
            f"Average cluster size: {average_size:0.2f}; largest cluster #{largest.id} with {largest.size} markers."
        )
    for cluster in clusters:
        min_lat, min_lon, max_lat, max_lon = cluster.bounding_box
        bbox_repr = f"({min_lat:.5f},{min_lon:.5f})-({max_lat:.5f},{max_lon:.5f})"
        print(
            f"  Cluster {cluster.id:02d}: size={cluster.size:3d} centroid=({cluster.centroid_lat:.5f}, {cluster.centroid_lon:.5f}) "
            f"radius={cluster.radius_km:.3f}km density={cluster.density:.2f}/km^2 bbox={bbox_repr}"
        )
        if cluster.average_value is not None:
            stats_parts = [f"avg={cluster.average_value:.2f}"]
            if cluster.median_value is not None:
                stats_parts.append(f"median={cluster.median_value:.2f}")
            if cluster.min_value is not None and cluster.max_value is not None:
                stats_parts.append(f"range={cluster.min_value:.2f}-{cluster.max_value:.2f}")
            if cluster.value_std is not None:
                stats_parts.append(f"std={cluster.value_std:.2f}")
            print(f"    values: {' | '.join(stats_parts)}")
        if cluster.value_outliers:
            threshold_note = "" if not z_threshold or z_threshold <= 0 else f" (z>{z_threshold:.2f})"
            print(f"    value outliers{threshold_note}:")
            for point_id, value, score in cluster.value_outliers:
                print(f"      point={point_id} value={value:.2f} z={score:.2f}")
        print(f"    members: {cluster.member_ids}")
    if show_noise and noise:
        print("Noise markers:")
        for marker in noise:
            value_repr = "n/a" if marker.value is None else f"{marker.value:.2f}"
            print(
                f"  point={marker.id} lat={marker.lat:.5f} lon={marker.lon:.5f} value={value_repr}"
            )


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


def _run_cluster(args: argparse.Namespace) -> None:
    if args.demo:
        points = _demo_cluster_dataset()
    else:
        points = _load_marker_points(args.coords, args.coord_file)
    z_threshold = args.cluster_value_z_threshold
    if z_threshold is not None and z_threshold <= 0:
        z_threshold = None
    clusters, noise = smart_marker_clusters(
        points,
        base_radius_km=args.cluster_base_radius,
        zoom_level=args.cluster_zoom,
        min_cluster_size=args.cluster_min_size,
        reference_zoom=args.cluster_zoom_reference,
        zoom_decay=args.cluster_zoom_decay,
        value_z_threshold=z_threshold,
        value_min_points=args.cluster_value_min_points,
    )
    _print_cluster_report(clusters, noise, z_threshold, args.cluster_show_noise)


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
    parser = argparse.ArgumentParser(
        description="Statistical anomaly detection and smart marker clustering utility (DEV-7 & DEV-12)."
    )
    parser.add_argument(
        "--task",
        choices=["detect", "cluster"],
        default="detect",
        help="Choose between numeric anomaly detection or geospatial marker clustering.",
    )
    # Detection options
    parser.add_argument(
        "--method",
        choices=["zscore", "iqr"],
        default="zscore",
        help="Detection method when --task detect is selected.",
    )
    parser.add_argument(
        "--threshold",
        type=float,
        default=3.0,
        help="Z-score threshold (used with --method zscore).",
    )
    parser.add_argument(
        "--multiplier",
        type=float,
        default=1.5,
        help="IQR multiplier (used with --method iqr).",
    )
    parser.add_argument("--values", type=str, help="Comma or whitespace separated numeric values.")
    parser.add_argument("--labels", type=str, help="Optional comma separated anomaly labels (0/1) for metrics.")
    parser.add_argument(
        "--file",
        type=pathlib.Path,
        help="Path to a text or CSV file containing numeric values.",
    )
    # Clustering options
    parser.add_argument(
        "--coords",
        type=str,
        help="Inline lat,lon[,value] markers separated by newlines or semicolons.",
    )
    parser.add_argument(
        "--coord-file",
        type=pathlib.Path,
        help="File with lat,lon[,value] markers for clustering.",
    )
    parser.add_argument(
        "--cluster-base-radius",
        type=float,
        default=0.6,
        help="Base clustering radius in kilometres (before zoom scaling).",
    )
    parser.add_argument(
        "--cluster-zoom",
        type=int,
        help="Optional map zoom level for adaptive radius scaling.",
    )
    parser.add_argument(
        "--cluster-zoom-reference",
        type=int,
        default=12,
        help="Zoom level where the base radius applies without scaling.",
    )
    parser.add_argument(
        "--cluster-zoom-decay",
        type=float,
        default=0.6,
        help="Radius decay factor applied for each zoom level increase.",
    )
    parser.add_argument(
        "--cluster-min-size",
        type=int,
        default=3,
        help="Minimum number of markers required to form a cluster.",
    )
    parser.add_argument(
        "--cluster-value-z-threshold",
        type=float,
        default=2.5,
        help="Z-score threshold for detecting value outliers inside clusters (<=0 disables).",
    )
    parser.add_argument(
        "--cluster-value-min-points",
        type=int,
        default=3,
        help="Minimum valued markers needed before running intra-cluster z-score detection.",
    )
    parser.add_argument(
        "--cluster-show-noise",
        action="store_true",
        help="List markers considered noise/outliers by the clustering step.",
    )
    parser.add_argument("--demo", action="store_true", help="Run the built-in synthetic demo dataset.")
    return parser


def main() -> None:
    parser = _build_parser()
    args = parser.parse_args()
    if args.task == "cluster":
        _run_cluster(args)
    else:
        _run_detection(args)


if __name__ == "__main__":
    main()
