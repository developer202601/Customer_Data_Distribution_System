@forelse($regionAdmins as $admin)
    <tr>
        <td><strong>{{ $admin->username }}</strong></td>
        <td>{{ $admin->name ?? '—' }}</td>
        <td>{{ $admin->assignment ?? '—' }}</td>
        <td>
            @if($admin->status)
                <span class="badge bg-success">Active</span>
            @else
                <span class="badge bg-secondary">Inactive</span>
            @endif
        </td>
        <td><small>{{ $admin->created_at?->format('M d, Y') ?? '—' }}</small></td>
        <td class="text-end">
            <a href="{{ route('cc.super.rb_regions.edit', $admin->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="text-center text-muted py-4">No RB region admins found</td>
    </tr>
@endforelse
