#!/usr/bin/env python
"""Fast master workbook validation for CDDS.

Validates:
- required header columns exist (PHP-normalised)
- required values are non-empty for selected columns
- duplicates on PRODUCT_LABEL (case-insensitive, trimmed)
- NEW_ARREARS_* column exists
- arrears column values are numeric or "-" (commas/spaces allowed)

Outputs a single JSON object to stdout.
Writes a CSV report when validation fails.

Exit codes:
- 0: validation ran successfully (pass or fail)
- 2: unexpected/runtime error (script could not run)
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from dataclasses import dataclass
from typing import Any, Dict, List, Optional, Tuple


@dataclass(frozen=True)
class ValidationConfig:
    required_value_columns: List[str]
    required_header_columns: List[str]
    dedupe_column: str
    arrears_prefix: str
    max_ui_errors: int
    max_report_rows: int


def _norm_col(col: str) -> str:
    return (col or "").strip().upper()


def _norm_col_php(col: str) -> str:
    value = (col or "").strip().upper()
    value = "_".join(value.split())
    out = []
    last_us = False
    for ch in value:
        if "A" <= ch <= "Z" or "0" <= ch <= "9":
            out.append(ch)
            last_us = False
        else:
            if not last_us:
                out.append("_")
                last_us = True

    normalised = "".join(out).strip("_")
    while "__" in normalised:
        normalised = normalised.replace("__", "_")

    if normalised == "RTO":
        return "RTOM"

    return normalised


def _safe_mkdirs(path: str) -> None:
    directory = os.path.dirname(path)
    if directory:
        os.makedirs(directory, exist_ok=True)


def _ensure_report_file(report_out: Optional[str]) -> None:
    if not report_out:
        return

    _safe_mkdirs(report_out)
    try:
        with open(report_out, "w", encoding="utf-8") as f:
            f.write("excel_row,column,error_code,value,first_seen_row\n")
    except OSError:
        pass


def _emit(payload: Dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=False))
    sys.stdout.write("\n")


def _fail_fast(message: str, *, errors: Optional[List[str]] = None, report_path: Optional[str] = None) -> None:
    _emit(
        {
            "status": "fail",
            "message": message,
            "errors": errors or [],
            "report_path": report_path,
        }
    )


def _read_excel_polars(path: str, columns: Optional[List[str]]) -> "pl.DataFrame":
    import polars as pl

    # drop_empty_rows=False preserves row-index mapping back to Excel.
    return pl.read_excel(
        source=path,
        engine="calamine",
        columns=columns,
        drop_empty_rows=False,
        drop_empty_cols=False,
        raise_if_empty=True,
    )


def _unique_normalised_columns(columns: Sequence[str]) -> List[str]:
    seen: Dict[str, int] = {}
    out: List[str] = []
    for i, col in enumerate(columns):
        base = _norm_col_php(str(col))
        if not base:
            base = f"COLUMN_{i+1}"

        if base in seen:
            seen[base] += 1
            out.append(f"{base}__{seen[base]}")
        else:
            seen[base] = 1
            out.append(base)
    return out


def _has_base_column(df_columns: Sequence[str], name: str) -> bool:
    prefix = f"{name}__"
    for c in df_columns:
        if c == name or str(c).startswith(prefix):
            return True
    return False


def _first_column(df_columns: Sequence[str], name: str) -> Optional[str]:
    prefix = f"{name}__"
    for c in df_columns:
        if c == name or str(c).startswith(prefix):
            return str(c)
    return None


def validate_master(
    input_path: str,
    report_out: Optional[str],
    config: ValidationConfig,
) -> Dict[str, Any]:
    try:
        import polars as pl  # noqa: F401
    except Exception as exc:  # pragma: no cover
        return {
            "status": "error",
            "message": f"Python dependency missing: {exc}",
            "errors": [],
        }

    required_values = [_norm_col(c) for c in config.required_value_columns if _norm_col(c)]
    required_headers = [_norm_col_php(c) for c in config.required_header_columns if _norm_col_php(c)]
    dedupe_col = _norm_col(config.dedupe_column)

    arrears_prefix = _norm_col_php(config.arrears_prefix or "NEW_ARREARS_")
    if not arrears_prefix.endswith("_"):
        arrears_prefix = arrears_prefix + "_"

    needed_headers = sorted(set(required_headers + required_values + ([dedupe_col] if dedupe_col else [])))

    ui_errors: List[str] = []

    # Read full workbook so we can normalise headers like PHP.
    try:
        df = _read_excel_polars(input_path, columns=None)
    except Exception as exc:
        return {
            "status": "error",
            "message": f"Unable to read workbook: {exc}",
            "errors": [],
        }

    # Polars returns data rows (header consumed). Excel row number = row_index + 2.
    try:
        import polars as pl

        df.columns = _unique_normalised_columns(df.columns)
        df = df.with_row_index("row_index").with_columns((pl.col("row_index") + 2).alias("excel_row"))

        row_count = int(df.height)

        # Missing header columns.
        missing_headers = [c for c in needed_headers if not _has_base_column(df.columns, c)]
        if missing_headers:
            _ensure_report_file(report_out)
            for c in missing_headers[: config.max_ui_errors]:
                ui_errors.append(f"Missing required column: {c}")
            if len(missing_headers) > config.max_ui_errors:
                ui_errors.append(f"Showing first {config.max_ui_errors} validation errors only.")

            return {
                "status": "fail",
                "message": "Missing required columns.",
                "row_count": int(df.height),
                "errors": ui_errors,
                "report_path": report_out,
                "error_count": int(len(missing_headers)),
            }

        # Ensure arrears column exists.
        arrears_cols = [c for c in df.columns if str(c).startswith(arrears_prefix)]
        if not arrears_cols:
            _ensure_report_file(report_out)
            ui_errors.append("The spreadsheet must include a NEW_ARREARS_YYYYMMDD column.")
            return {
                "status": "fail",
                "message": "Missing required columns.",
                "row_count": int(df.height),
                "errors": ui_errors,
                "report_path": report_out,
                "error_count": 1,
            }

        arrears_col = str(arrears_cols[0])

        # Normalize required + dedupe columns as strings for validation.
        normalized_cols: List[str] = []
        for base in sorted(set(required_values + ([dedupe_col] if dedupe_col else []) + [arrears_col])):
            col = _first_column(df.columns, base) if base != arrears_col else arrears_col
            if col and col in df.columns:
                normalized_cols.append(col)

        for col in normalized_cols:
            df = df.with_columns(
                pl.col(col)
                .cast(pl.Utf8, strict=False)
                .fill_null("")
                .str.strip_chars()
                .alias(col)
            )

        errors_frames: List[pl.DataFrame] = []
        total_errors = 0

        # Missing required values.
        for base in required_values:
            col = _first_column(df.columns, base)
            if not col:
                # This shouldn't happen after selection, but keep safe.
                if len(ui_errors) < config.max_ui_errors:
                    ui_errors.append(f"Missing required column: {base}")
                total_errors += 1
                continue

            miss = df.filter(pl.col(col) == "").select(
                [
                    pl.col("excel_row"),
                    pl.lit(base).alias("column"),
                    pl.lit("MISSING_REQUIRED").alias("error_code"),
                    pl.lit("").alias("value"),
                    pl.lit(None).alias("first_seen_row"),
                ]
            )

            miss_count = int(miss.height)
            total_errors += miss_count

            if miss_count > 0 and len(ui_errors) < config.max_ui_errors:
                # Pull only as many as needed for UI.
                sample_rows = miss.select(pl.col("excel_row")).head(config.max_ui_errors - len(ui_errors)).to_series().to_list()
                for excel_row in sample_rows:
                    ui_errors.append(f"Row {excel_row}, column {base}: value is required.")

            if miss_count > 0:
                # Cap report rows per column to avoid huge memory.
                errors_frames.append(miss.head(config.max_report_rows))

        # Duplicate PRODUCT_LABEL.
        if dedupe_col:
            col = _first_column(df.columns, dedupe_col)
            if not col:
                if len(ui_errors) < config.max_ui_errors:
                    ui_errors.append(f"Missing required column: {dedupe_col}")
                total_errors += 1
            else:
                key_expr = pl.col(col).str.to_lowercase().alias("dedupe_key")
                key_df = df.select(
                    [
                        pl.col("excel_row"),
                        pl.col(col).alias("value"),
                        key_expr,
                    ]
                ).filter(pl.col("dedupe_key") != "")

                # Find first occurrence per key.
                firsts = key_df.group_by("dedupe_key").agg(
                    [
                        pl.first("excel_row").alias("first_seen_row"),
                        pl.len().alias("count"),
                    ]
                )

                dup_keys = firsts.filter(pl.col("count") > 1).select(["dedupe_key", "first_seen_row"])

                if dup_keys.height > 0:
                    dup_rows = key_df.join(dup_keys, on="dedupe_key", how="inner")
                    # Keep only rows after first occurrence.
                    dup_rows = dup_rows.filter(pl.col("excel_row") != pl.col("first_seen_row"))
                    dup_rows = dup_rows.select(
                        [
                            pl.col("excel_row"),
                            pl.lit(dedupe_col).alias("column"),
                            pl.lit("DUPLICATE").alias("error_code"),
                            pl.col("value"),
                            pl.col("first_seen_row"),
                        ]
                    )

                    dup_count = int(dup_rows.height)
                    total_errors += dup_count

                    if dup_count > 0 and len(ui_errors) < config.max_ui_errors:
                        sample = dup_rows.head(config.max_ui_errors - len(ui_errors)).to_dicts()
                        for entry in sample:
                            excel_row = entry.get("excel_row")
                            first_seen = entry.get("first_seen_row")
                            value = entry.get("value")
                            ui_errors.append(
                                f"Row {excel_row}, column {dedupe_col}: duplicate value already found at row {first_seen}."
                                + (f" (value: {value})" if value else "")
                            )

                    if dup_count > 0:
                        errors_frames.append(dup_rows.head(config.max_report_rows))

        # Arrears numeric integrity (commas/spaces allowed, "-" allowed).
        # Only check the first detected arrears column.
        if arrears_col in df.columns:
            normalized = (
                pl.col(arrears_col)
                .cast(pl.Utf8, strict=False)
                .fill_null("")
                .str.strip_chars()
                .alias("arrears_raw")
            )

            df_ar = df.with_columns(normalized)
            invalid = (
                df_ar.filter(
                    (pl.col("arrears_raw") != "")
                    & (pl.col("arrears_raw") != "-")
                    & (
                        pl.col("arrears_raw")
                        .str.replace_all(r"[ ,]", "")
                        .str.contains(r"^-?\d+(?:\.\d+)?$")
                        .not_()
                    )
                )
                .select(
                    [
                        pl.col("excel_row"),
                        pl.lit("NEW_ARREARS_*").alias("column"),
                        pl.lit("INVALID_NUMBER").alias("error_code"),
                        pl.col("arrears_raw").alias("value"),
                        pl.lit(None).alias("first_seen_row"),
                    ]
                )
            )

            invalid_count = int(invalid.height)
            total_errors += invalid_count

            if invalid_count > 0 and len(ui_errors) < config.max_ui_errors:
                sample = invalid.select(pl.col("excel_row")).head(config.max_ui_errors - len(ui_errors)).to_series().to_list()
                for excel_row in sample:
                    ui_errors.append(f"Row {excel_row}, column NEW_ARREARS_*: expected numeric value or \"-\".")

            if invalid_count > 0:
                errors_frames.append(invalid.head(config.max_report_rows))

        if total_errors > 0:
            report_written = 0
            if report_out:
                # Combine and cap report size globally.
                if errors_frames:
                    report_df = pl.concat(errors_frames, how="vertical_relaxed")
                    if report_df.height > config.max_report_rows:
                        report_df = report_df.head(config.max_report_rows)
                    report_df.write_csv(report_out)
                    report_written = int(report_df.height)
                else:
                    _ensure_report_file(report_out)
                    report_written = 0

            message = "Master dataset validation failed."
            if len(ui_errors) >= config.max_ui_errors:
                ui_errors.append(f"Showing first {config.max_ui_errors} validation errors only.")

            return {
                "status": "fail",
                "message": message,
                "row_count": row_count,
                "errors": ui_errors,
                "report_path": report_out,
                "error_count": int(total_errors),
                "report_rows": report_written,
            }

        # If validation passes, optionally remove any stale report.
        if report_out and os.path.exists(report_out):
            try:
                os.remove(report_out)
            except OSError:
                pass

        return {
            "status": "pass",
            "message": "Validation passed.",
            "row_count": row_count,
            "errors": [],
        }

    except Exception as exc:
        return {
            "status": "error",
            "message": f"Validation error: {exc}",
            "errors": [],
        }


def main(argv: List[str]) -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True, help="Absolute path to master workbook (.xlsx)")
    parser.add_argument("--report-out", required=False, default=None, help="Absolute path to write CSV error report")
    parser.add_argument("--required", default="RUN_DATE,ACCOUNT_NUM,PRODUCT_LABEL")
    parser.add_argument("--required-columns", default="")
    parser.add_argument("--dedupe", default="PRODUCT_LABEL")
    parser.add_argument("--arrears-prefix", default="NEW_ARREARS_")
    parser.add_argument("--max-ui-errors", type=int, default=20)
    parser.add_argument("--max-report-rows", type=int, default=50000)

    args = parser.parse_args(argv)

    input_path = args.input
    if not os.path.isfile(input_path):
        _emit({"status": "error", "message": f"Input file not found: {input_path}", "errors": []})
        return 2

    required = [c.strip() for c in str(args.required).split(",") if c.strip()]
    required_columns = [c.strip() for c in str(args.required_columns).split(",") if c.strip()]
    dedupe = str(args.dedupe).strip() if args.dedupe else ""

    config = ValidationConfig(
        required_value_columns=required,
        required_header_columns=required_columns,
        dedupe_column=dedupe,
        arrears_prefix=str(args.arrears_prefix or "NEW_ARREARS_") ,
        max_ui_errors=max(1, int(args.max_ui_errors)),
        max_report_rows=max(1, int(args.max_report_rows)),
    )

    result = validate_master(input_path, args.report_out, config)

    status = result.get("status")
    if status == "error":
        # Hard errors should be non-zero so Laravel can treat as infrastructure failure.
        _emit(result)
        return 2

    _emit(result)
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
