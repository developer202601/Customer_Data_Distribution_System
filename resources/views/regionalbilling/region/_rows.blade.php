@forelse($rtomAdmins as $admin)
    <tr>
        <td><strong>{{ $admin->username }}</strong></td>
        <td>{{ $admin->name ?? '—' }}</td>
        <td>{{ $admin->assignment }}</td>
        <td><small>{{ $admin->created_at?->format('M d, Y') ?? '—' }}</small></td>
        <td class="text-end">
            <a href="{{ route('rb.region.edit_admin', $admin->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5" class="text-center text-muted py-4">No RTO admins found</td>
    </tr>
@endforelse
