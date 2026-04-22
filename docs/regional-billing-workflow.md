# Regional Billing Centre Workflow

## Overview
Regional Billing Centre (RBC) uses the master dataset and shares UI patterns with the call center module, but it distributes only rows assigned to the "regional billing center" segment. Reports are split by region (Metro, REGION 01, REGION 02, REGION 03) and then by RTOM and callers.

This workflow supports a regional admin stop gate that can prevent deeper distribution unless an inclusion or exclusion file is applied.

## Roles and Hierarchy
- Super admin: creates regional billing centre admins.
- Regional billing centre admin: creates RTOM admins for their region and controls the stop gate.
- RTOM admin: creates callers and distributes rows to callers.
- Caller: receives assignments and logs outcomes.

All roles reuse the existing call center UI layouts with role-based filtering and slightly different labels.

## Data Sources
- Base rows come from master_dataset_rows where assigned_to = "regional billing center".
- Region is determined by the region column (Metro, REGION 01, REGION 02, REGION 03).
- RTOM is determined by the rtom column.

## Report Types
A report_type column is added to the call_center_* tables to distinguish report data:
- call-center
- regional-billing

The regional billing report is created from the region-billing export bucket and stored with report_type = "regional-billing".

## Stop Gate (Regional Admin)
Regional admins can enable a stop option that blocks distribution to lower levels until a file is applied.

Rules:
- One file type per process: inclusion or exclusion.
- The user must select the file type before upload.
- Each file row may contain any of the following columns:
  - CUSTOMER_REF
  - PRODUCT_LABEL
  - ACCOUNT_NUM
- If a row has multiple identifiers, match by any available identifier.
- If a row has no identifier, ignore it.

Behavior:
- Inclusion file: only rows listed in the file are allowed for distribution. All other rows are excluded.
- Exclusion file: rows listed in the file are removed from the derived report; all others remain.

UI display must show:
- Allowed rows (after applying the file)
- Removed rows (after applying the file)
- Identifiers from the file that were not found in the derived report

## Passing to RTOM Admins
After regional admin finalization, the report is passed to RTOM admins. RTOM admins can:
- Auto-distribute rows to callers
- Manually adjust caller target counts

## Manual Caller Adjustment Logic
Default distribution is equal across callers. When one or more callers are adjusted:
1. Lock adjusted callers to the specified counts.
2. Compute remaining rows = total - sum(adjusted counts).
3. Auto-distribute the remaining rows evenly across non-adjusted callers.
4. Ensure the sum of all caller counts equals the total rows.

Example:
- 100 customers, 4 callers -> default 25 each.
- Caller A adjusted to 10.
- Remaining 90 rows distributed across the other 3 callers = 30 each.

## Implementation Notes
- Reuse call center controllers and views, but filter by report_type = "regional-billing" and system = "rb".
- Use the same assignment and interaction tables, but keep report data isolated by report_type.
- Regional admin review UI is adapted from the call center regional review page.
