#!/usr/bin/env python3
"""Python ingestion for CDDS master dataset.

Reads the extracted master workbook (.xlsx) using Polars + calamine/fastexcel,
maps columns to the `master_dataset_rows_staging` schema, and bulk inserts rows.

It also writes progress + workbook metadata to the JSON status file so Laravel
can display more accurate messages during `python_running`.

DB connection is provided via environment variables (set by Laravel):
- CDDS_DB_HOST
- CDDS_DB_PORT
- CDDS_DB_DATABASE
- CDDS_DB_USERNAME
- CDDS_DB_PASSWORD

Exit codes:
- 0: ingestion completed successfully
- 2: infrastructure/runtime failure (dependency missing, DB connect failure, etc.)
"""

from __future__ import annotations

import argparse
import json
import sys
from datetime import date, datetime, timezone
from pathlib import Path
import os
import re
from typing import Any, Dict, Iterable, List, Optional, Sequence, Tuple
import csv


def _norm_header(header: str) -> str:
    value = (header or "").strip().upper()
    value = re.sub(r"\s+", "_", value)
    value = re.sub(r"[^A-Z0-9_]", "_", value)
    value = re.sub(r"_+", "_", value)
    if value == "RTO":
        return "RTOM"
    return value


def _parse_money(value: Any) -> Optional[float]:
    if value is None:
        return None
    text = str(value).strip()
    if text == "" or text == "-":
        return None
    numeric = text.replace(",", "").replace(" ", "")
    try:
        return float(numeric)
    except ValueError:
        return None


def _parse_decimal(value: Any) -> Optional[float]:
    if value is None:
        return None
    text = str(value).strip()
    if text == "":
        return None
    try:
        return float(text)
    except ValueError:
        return None


def _parse_int(value: Any) -> Optional[int]:
    if value is None:
        return None
    text = str(value).strip()
    if text == "":
        return None
    try:
        return int(float(text))
    except ValueError:
        return None


def _parse_date(value: Any) -> Optional[datetime]:
    if value is None:
        return None

    if isinstance(value, datetime):
        return value

    if isinstance(value, date):
        return datetime.combine(value, datetime.min.time())

    text = str(value).strip()
    if text == "":
        return None

    # Normalize some common separators produced by Excel engines.
    candidate = text.replace(".", ":")

    for fmt in (
        "%Y-%m-%d",
        "%Y/%m/%d",
        "%d-%m-%Y",
        "%d/%m/%Y",
        "%Y-%m-%d %H:%M",
        "%Y-%m-%d %H:%M:%S",
        "%d/%m/%Y %H:%M",
        "%d/%m/%Y %H:%M:%S",
    ):
        try:
            return datetime.strptime(candidate, fmt)
        except ValueError:
            pass

    if text.isdigit():
        try:
            return datetime.fromtimestamp(int(text))
        except (OSError, OverflowError, ValueError):
            return None

    return None


def _parse_run_date(value: Any) -> Optional[datetime]:
    if value is None:
        return None

    if isinstance(value, datetime):
        return value

    text = str(value).strip()
    if text == "":
        return None

    candidate = text.replace(".", ":")
    for fmt in (
        "%Y-%m-%d %H:%M",
        "%Y-%m-%d %H:%M:%S",
        "%Y-%m-%d",
        "%d/%m/%Y %H:%M",
        "%d/%m/%Y %H:%M:%S",
        "%d/%m/%Y",
        "%d-%m-%Y %H:%M",
        "%d-%m-%Y %H:%M:%S",
        "%d-%m-%Y",
    ):
        try:
            return datetime.strptime(candidate, fmt)
        except ValueError:
            pass

    return None


def _is_vip(value: str) -> bool:
    if not value:
        return False
    return re.match(r"^VIP(\s*-\s*.+)?$", value.strip(), flags=re.IGNORECASE) is not None


def _evaluate_auto_exclusion(medium: str, latest_product_status: str, arrears_value: Optional[float], arrears_column: str) -> Optional[str]:
    medium_norm = (medium or "").strip().upper()
    if medium_norm == "" or medium_norm not in ("COPPER", "FTTH"):
        value = medium_norm if medium_norm != "" else "blank"
        return f"AUTO: MEDIUM is {value} (requires COPPER or FTTH)"

    status_norm = (latest_product_status or "").strip().upper()
    if status_norm != "OK":
        value = status_norm if status_norm != "" else "blank"
        return f"AUTO: LATEST_PRODUCT_STATUS is {value} (requires OK)"

    arrears = float(arrears_value or 0.0)
    if arrears <= 2400:
        col = arrears_column or "NEW_ARREARS"
        return f"AUTO: {col} <= 2400"

    return None


def _read_excel(path: str) -> "pl.DataFrame":
    import polars as pl

    return pl.read_excel(
        source=path,
        engine="calamine",
        drop_empty_rows=False,
        drop_empty_cols=False,
        raise_if_empty=True,
    )


def _has_base_column(columns: Sequence[str], name: str) -> bool:
    prefix = f"{name}__"
    for c in columns:
        if c == name or str(c).startswith(prefix):
            return True
    return False


def _first_column(columns: Sequence[str], name: str) -> Optional[str]:
    prefix = f"{name}__"
    for c in columns:
        if c == name or str(c).startswith(prefix):
            return str(c)
    return None


def _ensure_report_header(path: str) -> None:
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    try:
        with open(path, "w", newline="", encoding="utf-8") as f:
            writer = csv.writer(f)
            writer.writerow(["excel_row", "column", "error_code", "value", "first_seen_row"])
    except OSError:
        pass


def _write_report_rows(path: str, rows: List[List[Any]]) -> None:
    Path(path).parent.mkdir(parents=True, exist_ok=True)
    try:
        with open(path, "w", newline="", encoding="utf-8") as f:
            writer = csv.writer(f)
            writer.writerow(["excel_row", "column", "error_code", "value", "first_seen_row"])
            writer.writerows(rows)
    except OSError:
        pass


def _validate_workbook(
    df: "pl.DataFrame",
    *,
    required_headers: Sequence[str],
    required_values: Sequence[str],
    dedupe_column: str,
    arrears_prefix: str,
    max_ui_errors: int,
    max_report_rows: int,
    report_out: Optional[str],
) -> Dict[str, Any]:
    import polars as pl

    required_headers = [str(x).strip().upper() for x in required_headers if str(x).strip()]
    required_values = [str(x).strip().upper() for x in required_values if str(x).strip()]
    dedupe_column = str(dedupe_column or "").strip().upper()

    arrears_prefix = _norm_header(arrears_prefix or "NEW_ARREARS_")
    if not arrears_prefix.endswith("_"):
        arrears_prefix += "_"

    # Add excel row number mapping.
    df = df.with_row_index("row_index").with_columns((pl.col("row_index") + 2).alias("excel_row"))
    row_count = int(df.height)

    ui_errors: List[str] = []
    report_rows: List[List[Any]] = []

    # Required headers.
    missing_headers = [c for c in required_headers if not _has_base_column(df.columns, c)]
    if missing_headers:
        for c in missing_headers[:max_ui_errors]:
            ui_errors.append(f"Missing required column: {c}")
        if report_out:
            _ensure_report_header(report_out)
        return {
            "status": "failed_validation",
            "message": "Missing required columns.",
            "row_count": row_count,
            "errors": ui_errors,
        }

    # Ensure arrears column exists.
    arrears_cols = [c for c in df.columns if str(c).startswith(arrears_prefix)]
    if not arrears_cols:
        ui_errors.append("The spreadsheet must include a NEW_ARREARS_YYYYMMDD column.")
        if report_out:
            _ensure_report_header(report_out)
        return {
            "status": "failed_validation",
            "message": "Missing required columns.",
            "row_count": row_count,
            "errors": ui_errors,
        }

    arrears_col = str(arrears_cols[0])

    # Normalize columns as trimmed strings for validations.
    validate_cols: List[str] = []
    for base in required_values:
        col = _first_column(df.columns, base)
        if col:
            validate_cols.append(col)
    if dedupe_column:
        col = _first_column(df.columns, dedupe_column)
        if col:
            validate_cols.append(col)
    validate_cols.append(arrears_col)

    for col in sorted(set(validate_cols)):
        df = df.with_columns(
            pl.col(col).cast(pl.Utf8, strict=False).fill_null("").str.strip_chars().alias(col)
        )

    error_frames: List[pl.DataFrame] = []
    total_errors = 0

    # Missing required values.
    for base in required_values:
        col = _first_column(df.columns, base)
        if not col:
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
        if miss_count > 0 and len(ui_errors) < max_ui_errors:
            sample_rows = (
                miss.select(pl.col("excel_row"))
                .head(max_ui_errors - len(ui_errors))
                .to_series()
                .to_list()
            )
            for excel_row in sample_rows:
                ui_errors.append(f"Row {excel_row}, column {base}: value is required.")
        if miss_count > 0:
            error_frames.append(miss.head(max_report_rows))

    # Duplicate PRODUCT_LABEL.
    if dedupe_column:
        col = _first_column(df.columns, dedupe_column)
        if col:
            key_df = (
                df.select(
                    [
                        pl.col("excel_row"),
                        pl.col(col).alias("value"),
                        pl.col(col).str.to_lowercase().alias("dedupe_key"),
                    ]
                )
                .filter(pl.col("dedupe_key") != "")
            )

            firsts = key_df.group_by("dedupe_key").agg(
                [pl.first("excel_row").alias("first_seen_row"), pl.len().alias("count")]
            )

            dup_keys = firsts.filter(pl.col("count") > 1).select([
                "dedupe_key",
                "first_seen_row",
            ])

            if dup_keys.height > 0:
                dup_rows = key_df.join(dup_keys, on="dedupe_key", how="inner")
                dup_rows = dup_rows.filter(pl.col("excel_row") != pl.col("first_seen_row"))
                dup_rows = dup_rows.select(
                    [
                        pl.col("excel_row"),
                        pl.lit(dedupe_column).alias("column"),
                        pl.lit("DUPLICATE").alias("error_code"),
                        pl.col("value"),
                        pl.col("first_seen_row"),
                    ]
                )

                dup_count = int(dup_rows.height)
                total_errors += dup_count

                if dup_count > 0 and len(ui_errors) < max_ui_errors:
                    sample = dup_rows.head(max_ui_errors - len(ui_errors)).to_dicts()
                    for entry in sample:
                        excel_row = entry.get("excel_row")
                        first_seen = entry.get("first_seen_row")
                        value = entry.get("value")
                        ui_errors.append(
                            f"Row {excel_row}, column {dedupe_column}: duplicate value already found at row {first_seen}."
                            + (f" (value: {value})" if value else "")
                        )

                if dup_count > 0:
                    error_frames.append(dup_rows.head(max_report_rows))

    # Arrears numeric integrity.
    df_ar = df.with_columns(pl.col(arrears_col).alias("arrears_raw"))
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
    if invalid_count > 0 and len(ui_errors) < max_ui_errors:
        sample_rows = (
            invalid.select(pl.col("excel_row"))
            .head(max_ui_errors - len(ui_errors))
            .to_series()
            .to_list()
        )
        for excel_row in sample_rows:
            ui_errors.append(f"Row {excel_row}, column NEW_ARREARS_*: expected numeric value or \"-\".")
    if invalid_count > 0:
        error_frames.append(invalid.head(max_report_rows))

    if total_errors > 0:
        if len(ui_errors) >= max_ui_errors:
            ui_errors.append(f"Showing first {max_ui_errors} validation errors only.")

        if report_out:
            try:
                report_df = pl.concat(error_frames, how="vertical_relaxed") if error_frames else None
                if report_df is not None and int(report_df.height) > max_report_rows:
                    report_df = report_df.head(max_report_rows)
                if report_df is not None:
                    Path(report_out).parent.mkdir(parents=True, exist_ok=True)
                    report_df.write_csv(report_out)
                else:
                    _ensure_report_header(report_out)
            except Exception:
                _ensure_report_header(report_out)

        return {
            "status": "failed_validation",
            "message": "Master dataset validation failed.",
            "row_count": row_count,
            "errors": ui_errors,
        }

    # Validation pass: remove stale report if present.
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


def _connect_mysql_from_env():
    try:
        import pymysql
    except Exception as exc:  # pragma: no cover
        raise RuntimeError(f"Missing dependency pymysql: {exc}")

    host = os.environ.get("CDDS_DB_HOST") or "127.0.0.1"
    port_raw = os.environ.get("CDDS_DB_PORT") or "3306"
    user = os.environ.get("CDDS_DB_USERNAME") or "root"
    password = os.environ.get("CDDS_DB_PASSWORD") or "0000"
    database = os.environ.get("CDDS_DB_DATABASE") or "cdds"

    if not host or not user or not database:
        raise RuntimeError("Database environment variables are missing (CDDS_DB_HOST/USERNAME/DATABASE).")

    try:
        port = int(port_raw)
    except ValueError:
        port = 3306

    return pymysql.connect(
        host=host,
        port=port,
        user=user,
        password=password,
        database=database,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=pymysql.cursors.Cursor,
    )


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Hybrid ingestion bridge")
    parser.add_argument("--process", required=True, type=int, help="Process ID")
    parser.add_argument("--manifest", required=True, help="Path to manifest JSON")
    parser.add_argument("--status", required=True, help="Path to status JSON")
    parser.add_argument(
        "--abort-flag",
        required=False,
        default="",
        help="Path to an abort flag file. If present, the script cancels gracefully.",
    )
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

    abort_flag = str(getattr(args, "abort_flag", "") or "").strip()

    def _abort_requested() -> bool:
        return bool(abort_flag) and os.path.exists(abort_flag)

    def _cancel(stage: str, *, processed_rows: int = 0, total_rows: int = 0) -> int:
        payload = {
            "status": "canceled",
            "message": f"Canceled by user ({stage}).",
            "process_id": int(manifest.get("process_id") or args.process),
            "manifest_path": args.manifest,
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "progress": {"processed_rows": int(processed_rows), "total_rows": int(total_rows)},
        }
        try:
            write_status(args.status, payload)
        except Exception:
            pass
        return 130

    workbook_path = str(manifest.get("master_workbook_full_path") or "").strip()
    if not workbook_path:
        raise RuntimeError("Manifest is missing master_workbook_full_path")
    if not os.path.isfile(workbook_path):
        raise FileNotFoundError(f"Workbook not found: {workbook_path}")

    process_id = int(manifest.get("process_id") or args.process)

    report_out = manifest.get("validation_report_full_path")
    report_out = str(report_out).strip() if report_out else None

    required_headers = manifest.get("required_columns") or []
    required_values = manifest.get("required_row_columns") or []
    dedupe_column = str(manifest.get("dedupe_column") or "PRODUCT_LABEL")
    arrears_prefix = str(manifest.get("arrears_prefix") or "NEW_ARREARS_")

    max_ui_errors = int(manifest.get("max_ui_errors") or 20)
    max_report_rows = int(manifest.get("max_report_rows") or 50000)

    status_base: Dict[str, Any] = {
        "status": "running",
        "message": "Fast-validating master dataset (Python)…",
        "process_id": process_id,
        "manifest_path": args.manifest,
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "progress": {"processed_rows": 0, "total_rows": 0},
        "row_counts": {"staging_inserted": 0, "excluded": 0},
        "workbook": {},
    }
    write_status(args.status, status_base)

    if _abort_requested():
        return _cancel("startup")

    try:
        import polars as pl
    except Exception as exc:
        raise RuntimeError(f"Missing dependency polars/fastexcel: {exc}")

    df = _read_excel(workbook_path)

    if _abort_requested():
        return _cancel("workbook_read")

    # Normalize headers to match PHP behaviour.
    original_cols = list(df.columns)
    norm_cols: List[str] = []
    counts: Dict[str, int] = {}
    for idx, col in enumerate(original_cols):
        base = _norm_header(str(col))
        if base == "":
            base = f"COLUMN_{idx+1}"
        n = base
        if n in counts:
            counts[n] += 1
            n = f"{base}__{counts[base]}"
        else:
            counts[n] = 1
        norm_cols.append(n)

    df.columns = norm_cols

    # Validate using the already-loaded DataFrame (single XLSX read).
    validation = _validate_workbook(
        df,
        required_headers=required_headers,
        required_values=required_values,
        dedupe_column=dedupe_column,
        arrears_prefix=arrears_prefix,
        max_ui_errors=max(1, max_ui_errors),
        max_report_rows=max(1, max_report_rows),
        report_out=report_out,
    )

    if validation.get("status") != "pass":
        status_payload = {
            "status": "failed_validation",
            "message": str(validation.get("message") or "Master dataset validation failed."),
            "process_id": process_id,
            "manifest_path": args.manifest,
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "row_count": int(validation.get("row_count") or 0),
            "errors": validation.get("errors") or [],
            "progress": {
                "processed_rows": int(validation.get("row_count") or 0),
                "total_rows": int(validation.get("row_count") or 0),
            },
        }
        write_status(args.status, status_payload)
        return 0

<<<<<<< HEAD
    if _abort_requested():
        return _cancel("validation", processed_rows=int(validation.get("row_count") or 0), total_rows=int(validation.get("row_count") or 0))
=======
    csv_path = str(manifest.get("master_csv_full_path") or "").strip()
    if csv_path:
        try:
            Path(csv_path).parent.mkdir(parents=True, exist_ok=True)
            df.write_csv(csv_path)
            status_base["workbook"]["csv_path"] = csv_path
        except Exception:
            pass
>>>>>>> 9f8f1c89d253f00daee6080d04751d48b116d696

    # Determine arrears + payments columns (now guaranteed present).
    arrears_candidates = [c for c in df.columns if str(c).startswith(_norm_header(arrears_prefix or "NEW_ARREARS_"))]
    arrears_col = arrears_candidates[0]
    arrears_secondary_col = arrears_candidates[1] if len(arrears_candidates) > 1 else None

    payments_candidates = [c for c in df.columns if str(c).startswith("PAYMENTS")]
    payments_col = payments_candidates[0] if payments_candidates else None

    # Parse arrears date and dataset month from column suffix.
    suffix = re.sub(r"[^0-9]", "", str(arrears_col).replace("NEW_ARREARS_", ""))
    arrears_date = None
    if len(suffix) >= 6:
        ymd = suffix[:8]
        for fmt in ("%Y%m%d", "%Y%m"):
            try:
                arrears_date = datetime.strptime(ymd, fmt).date()
                break
            except ValueError:
                pass

    dataset_month = arrears_date.strftime("%Y%m") if arrears_date else datetime.now().strftime("%Y%m")

    # Add excel row number column: row_index (0-based) + 2.
    df = df.with_row_index("row_index").with_columns((pl.col("row_index") + 2).alias("excel_row"))
    total_rows = int(df.height)

    # Prepare DB connection (only after validation passes).
    conn = _connect_mysql_from_env()
    try:
        if _abort_requested():
            return _cancel("before_db")

        with conn.cursor() as cur:
            cur.execute("DELETE FROM master_dataset_rows_staging WHERE process_id=%s", (process_id,))
        conn.commit()

        insert_columns = [
            "process_id",
            "payload",
            "run_date_raw",
            "run_date",
            "region",
            "rtom",
            "customer_ref",
            "account_num",
            "installment",
            "account_status",
            "acct_effect_dtm",
            "bill_seq",
            "product_label",
            "medium",
            "customer_segment",
            "address_name",
            "full_address",
            "latest_bill_mny",
            "new_arrears_value",
            "new_arrears_column",
            "payments_value",
            "payments_column",
            "new_arrears_secondary_value",
            "new_arrears_secondary_column",
            "mobile_contact_tel",
            "email_address",
            "credit_score",
            "credit_class_id",
            "credit_class_name",
            "bill_handling_code_name",
            "age_months",
            "sales_person",
            "account_manager",
            "slt_gl_sub_segment",
            "billing_centre",
            "province",
            "next_bill_dtm",
            "payment_due_dat",
            "bill_month",
            "latest_bill_dtm",
            "invoicing_co_id",
            "invoicing_co_name",
            "product_seq",
            "product_id",
            "product_name",
            "start_dat",
            "end_dat",
            "latest_product_status",
            "latest_effective_dtm",
            "bill_handling_code",
            "phone_number",
            "slt_business_line_value",
            "sales_channel",
            "excluded",
            "exclusion_reason",
            "exclusion_priority",
            "assigned_to",
            "created_at",
            "updated_at",
        ]

        placeholders = ",".join(["%s"] * len(insert_columns))
        sql = f"INSERT INTO master_dataset_rows_staging ({','.join(insert_columns)}) VALUES ({placeholders})"

        staged_inserted = 0
        excluded_count = 0
        first_run_date = None
        first_run_date_raw = None

        batch: List[Tuple[Any, ...]] = []
        batch_size = 1000
        now = datetime.now()

        # Iterate rows (includes potentially empty rows). Skip completely blank rows.
        for row in df.iter_rows(named=True):
            # Consider a row blank if all values are null/empty after string conversion.
            has_any = False
            for k, v in row.items():
                if k in ("row_index", "excel_row"):
                    continue
                if v is None:
                    continue
                if str(v).strip() != "":
                    has_any = True
                    break
            if not has_any:
                continue

            excel_row = int(row.get("excel_row") or 0)

            def col(name: str) -> str:
                val = row.get(name)
                return str(val).strip() if val is not None else ""

            run_date_raw = col("RUN_DATE")
            run_date_dt = _parse_run_date(run_date_raw)
            if first_run_date is None and run_date_dt is not None:
                first_run_date = run_date_dt
                first_run_date_raw = run_date_raw

            acct_effect_dt = _parse_date(col("ACCT_EFFECT_DTM"))
            next_bill_dt = _parse_date(col("NEXT_BILL_DTM"))
            payment_due_dt = _parse_date(col("PAYMENT_DUE_DAT"))
            latest_bill_dt = _parse_date(col("LATEST_BILL_DTM"))
            start_dt = _parse_date(col("START_DAT"))
            end_dt = _parse_date(col("END_DAT"))
            latest_effective_dt = _parse_date(col("LATEST_EFFECTIVE_DTM"))

            latest_status = col("LATEST_PRODUCT_STATUS")
            medium = col("MEDIUM")

            arrears_raw = row.get(arrears_col)
            arrears_value = _parse_money(arrears_raw)
            exclusion_reason = _evaluate_auto_exclusion(medium, latest_status, arrears_value, str(arrears_col))
            excluded = 1 if exclusion_reason else 0
            exclusion_priority = 5 if excluded else 0
            assigned_to = "Excluded" if excluded else None

            credit_class_name = col("CREDIT_CLASS_NAME")
            if not credit_class_name:
                credit_class_name = col("CUSTOMER_SEGMENT")

            if not excluded and _is_vip(credit_class_name):
                assigned_to = "VIP"

            if excluded:
                excluded_count += 1

            payload = json.dumps({"excel_row": excel_row}, ensure_ascii=False)

            record = (
                process_id,
                payload,
                run_date_raw or None,
                run_date_dt,
                col("REGION") or None,
                col("RTOM") or None,
                col("CUSTOMER_REF") or None,
                col("ACCOUNT_NUM") or None,
                col("INSTALLMENT") or None,
                col("ACCOUNT_STATUS") or None,
                acct_effect_dt.date() if acct_effect_dt else None,
                col("BILL_SEQ") or None,
                col("PRODUCT_LABEL") or None,
                medium or None,
                col("CUSTOMER_SEGMENT") or None,
                col("ADDRESS_NAME") or None,
                col("FULL_ADDRESS") or None,
                _parse_money(col("LATEST_BILL_MNY")),
                arrears_value,
                str(arrears_col),
                _parse_money(row.get(payments_col)) if payments_col else None,
                str(payments_col) if payments_col else None,
                _parse_money(row.get(arrears_secondary_col)) if arrears_secondary_col else None,
                str(arrears_secondary_col) if arrears_secondary_col else None,
                col("MOBILE_CONTACT_TEL") or None,
                col("EMAIL_ADDRESS") or None,
                _parse_decimal(col("CREDIT_SCORE")),
                col("CREDIT_CLASS_ID") or None,
                col("CREDIT_CLASS_NAME") or None,
                col("BILL_HANDLING_CODE_NAME") or None,
                _parse_int(col("AGE_MONTHS")),
                col("SALES_PERSON") or None,
                col("ACCOUNT_MANAGER") or None,
                col("SLT_GL_SUB_SEGMENT") or None,
                col("BILLING_CENTRE") or None,
                col("PROVINCE") or None,
                next_bill_dt.date() if next_bill_dt else None,
                payment_due_dt.date() if payment_due_dt else None,
                col("BILL_MONTH") or None,
                latest_bill_dt.date() if latest_bill_dt else None,
                col("INVOICING_CO_ID") or None,
                col("INVOICING_CO_NAME") or None,
                col("PRODUCT_SEQ") or None,
                col("PRODUCT_ID") or None,
                col("PRODUCT_NAME") or None,
                start_dt.date() if start_dt else None,
                end_dt.date() if end_dt else None,
                latest_status or None,
                latest_effective_dt.date() if latest_effective_dt else None,
                col("BILL_HANDLING_CODE") or None,
                col("PHONE_NUMBER") or None,
                col("SLT_BUSINESS_LINE_VALUE") or None,
                col("SALES_CHANNEL") or None,
                excluded,
                exclusion_reason,
                exclusion_priority,
                assigned_to,
                now,
                now,
            )

            batch.append(record)

            if len(batch) >= batch_size:
                with conn.cursor() as cur:
                    cur.executemany(sql, batch)
                conn.commit()
                staged_inserted += len(batch)
                batch = []

                if _abort_requested():
                    return _cancel("staging_insert", processed_rows=staged_inserted, total_rows=total_rows)

                status_base["message"] = "Ingesting master dataset (Python)…"
                status_base["progress"] = {"processed_rows": staged_inserted, "total_rows": total_rows}
                status_base["row_counts"] = {"staging_inserted": staged_inserted, "excluded": excluded_count}
                status_base["workbook"] = {
                    "arrears_column": str(arrears_col),
                    "arrears_date": arrears_date.isoformat() if arrears_date else None,
                    "dataset_month": dataset_month,
                }
                write_status(args.status, status_base)

        if batch:
            with conn.cursor() as cur:
                cur.executemany(sql, batch)
            conn.commit()
            staged_inserted += len(batch)

        if _abort_requested():
            return _cancel("staging_insert", processed_rows=staged_inserted, total_rows=total_rows)

        status_payload = {
            "status": "completed",
            "message": "Python ingestion staged rows successfully.",
            "process_id": process_id,
            "manifest_path": args.manifest,
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "progress": {"processed_rows": staged_inserted, "total_rows": total_rows},
            "row_counts": {"staging_inserted": staged_inserted, "excluded": excluded_count},
            "workbook": {
                "arrears_column": str(arrears_col),
                "arrears_date": arrears_date.isoformat() if arrears_date else None,
                "dataset_month": dataset_month,
            },
            "run_date": first_run_date.isoformat() if first_run_date else None,
            "run_date_raw": first_run_date_raw,
        }
        write_status(args.status, status_payload)
        return 0
    finally:
        try:
            conn.close()
        except Exception:
            pass


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
