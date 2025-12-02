#!/usr/bin/env python3
"""Placeholder ingestion script for the hybrid pipeline.

This script is expected to receive three arguments:
    --process=<id>
    --manifest=<absolute path to manifest json>
    --status=<absolute path to status json>

It currently performs minimal validation and writes a status file so that
Laravel can confirm the hand-off works. Replace the TODO section with the
actual ingestion logic (open the Excel file, stream records into the staging
table, apply exclusions, etc.).
"""

from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime, timezone
from pathlib import Path


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Hybrid ingestion bridge")
    parser.add_argument("--process", required=True, type=int, help="Process ID")
    parser.add_argument("--manifest", required=True, help="Path to manifest JSON")
    parser.add_argument("--status", required=True, help="Path to status JSON")
    return parser.parse_args()


def load_manifest(path: str) -> dict:
    manifest_path = Path(path)
    if not manifest_path.exists():
        raise FileNotFoundError(f"Manifest file not found: {manifest_path}")

    with manifest_path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def write_status(path: str, payload: dict) -> None:
    status_path = Path(path)
    status_path.parent.mkdir(parents=True, exist_ok=True)
    with status_path.open("w", encoding="utf-8") as handle:
        json.dump(payload, handle, indent=2, ensure_ascii=False)


def main() -> int:
    args = parse_args()
    manifest = load_manifest(args.manifest)

    # TODO: Implement ingestion logic here.
    #  1. Read manifest + master archive path
    #  2. Stream data into master_dataset_rows_staging
    #  3. Apply exclusion files
    #  4. Write detailed status updates

    status_payload = {
        "status": "completed",
        "message": "Placeholder ingestion finished without work.",
        "process_id": args.process,
        "manifest_path": args.manifest,
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "row_counts": {
            "staging_inserted": 0,
            "exclusions_applied": 0,
        },
    }
    write_status(args.status, status_payload)
    return 0


if __name__ == "__main__":  # pragma: no cover - script entry point
    try:
        sys.exit(main())
    except Exception as exc:  # pylint: disable=broad-except
        error_payload = {
            "status": "failed",
            "message": str(exc),
            "timestamp": datetime.now(timezone.utc).isoformat(),
        }
        if len(sys.argv) > 1:
            for arg in sys.argv:
                if arg.startswith("--status="):
                    _, status_path = arg.split("=", 1)
                    try:
                        write_status(status_path, error_payload)
                    except Exception:  # pragma: no cover
                        pass
        raise
