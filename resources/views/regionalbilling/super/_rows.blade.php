@forelse($regionAdmins as $admin)
    <tr>
        <td><strong>{{ $admin->username }}</strong></td>
        <td>{{ $admin->name ?? '—' }}</td>
        <td>
            <span class="badge {{ $admin->system === 'cc' ? 'bg-primary' : 'bg-success' }}">
                {{ $admin->system === 'cc' ? 'Call Center' : 'Regional Billing' }}
            </span>
        </td>
        <td>{{ $admin->assignment ?? '—' }}</td>
        <td><small>{{ $admin->created_at?->format('M d, Y') ?? '—' }}</small></td>
        <td class="text-end">
            <a href="{{ route('rb.regions.edit', $admin->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="text-center text-muted py-4">No region admins found</td>
    </tr>
@endforelse
