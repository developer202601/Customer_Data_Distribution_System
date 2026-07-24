#!/usr/bin/env python3
import sys
import json
import polars as pl

def main():
    if len(sys.argv) < 2:
        print(json.dumps({
            'status': 'error',
            'message': 'Usage: read_payment_excel.py <file.xlsx>'
        }))
        sys.exit(1)

    file_path = sys.argv[1]

    try:
        df = pl.read_excel(file_path, has_header=False)
        rows = df.rows()
    except Exception as e:
        print(json.dumps({
            'status': 'error',
            'message': f'Failed to open workbook: {e}'
        }))
        sys.exit(1)

    if not rows:
        print(json.dumps({
            'status': 'error',
            'message': 'No data found in workbook.'
        }))
        sys.exit(1)

    header_row = [str(h).strip() if h is not None else '' for h in rows[0]]
    upper_headers = [h.upper() for h in header_row]

    account_num_index = None
    payment_mny_index = None

    for idx, header in enumerate(upper_headers):
        if header == 'ACCOUNT_NUM':
            account_num_index = idx
        if header == 'ACCOUNT_PAYMENT_MNY':
            payment_mny_index = idx

    if account_num_index is None or payment_mny_index is None:
        found = ', '.join(header_row[:20])
        print(json.dumps({
            'status': 'error',
            'message': f"Missing required headers. Found: [{found}]"
        }))
        sys.exit(1)

    data_rows = []
    for row in rows[1:]:
        data_rows.append(list(row))

    print(json.dumps({
        'status': 'ok',
        'headers': header_row,
        'account_num_index': account_num_index,
        'payment_mny_index': payment_mny_index,
        'total_rows': len(data_rows),
        'data': data_rows
    }))

if __name__ == '__main__':
    main()
