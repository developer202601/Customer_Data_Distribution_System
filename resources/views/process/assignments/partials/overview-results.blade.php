@php
    $resultItems = isset($rows) ? $rows->items() : [];
    $assignmentLabels = $assignmentLabels ?? [];
    $resolveAssignment = function ($row) use ($assignmentLabels) {
        if ($row->excluded) { return 'Excluded'; }
        $value = $row->assigned_to;
        if ($value === null || $value === '') { return 'Unassigned'; }
        foreach ($assignmentLabels as $key => $label) {
            if (strtolower($value) === strtolower($key)) { return $label; }
        }
        return ucfirst($value);
    };
@endphp

<div class="process-table-container mt-4">
    <div class="table-responsive">
        <table class="table table-striped process-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Customer Reference</th>
                    <th scope="col">Account Number</th>
                    <th scope="col">Product Label</th>
                    <th scope="col">Business Line</th>
                    <th scope="col">Assignment</th>
                    <th scope="col" class="text-end">New Arrears (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($resultItems as $row)
                <tr>
                    <td>{{ $row->customer_ref ?? '—' }}</td>
                    <td>{{ $row->account_num ?? '—' }}</td>
                    <td>{{ $row->product_label ?? '—' }}</td>
                    <td>{{ $row->slt_business_line_value ?? '—' }}</td>
                    <td>{{ $resolveAssignment($row) }}</td>
                    <td class="text-end">{{ $row->new_arrears_value !== null ? number_format((float) $row->new_arrears_value, 2) : '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No records matched your search.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if(isset($rows) && $rows->hasPages())
<div class="mt-3">
    {{ $rows->links('pagination::bootstrap-5') }}
</div>
@endif
