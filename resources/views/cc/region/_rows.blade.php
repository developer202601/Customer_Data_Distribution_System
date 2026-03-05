@forelse($rtomAdmins as $a)
<tr>
    <td>{{ $a->username }}</td>
    <td>{{ $a->name ?? '-' }}</td>
    <td><span class="badge bg-secondary">{{ $a->assignment }}</span></td>
    <td class="text-nowrap text-muted">{{ $a->created_at ? \Carbon\Carbon::parse($a->created_at)->format('Y-m-d') : '-' }}</td>
    <td class="text-end">
        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('cc.region.edit_admin', $a) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Edit</a>
            @if($a->fixed)
                <button class="btn btn-sm btn-warning rounded-pill" disabled>Fixed</button>
            @else
                @if($a->status)
                    <button type="button" class="btn btn-sm btn-warning rounded-pill cc-disable-btn" data-action="{{ route('cc.users.disable', $a) }}" data-username="{{ $a->username }}">Disable</button>
                @else
                    <button type="button" class="btn btn-sm btn-success rounded-pill cc-enable-btn" data-action="{{ route('cc.users.enable', $a) }}" data-username="{{ $a->username }}">Enable</button>
                @endif
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill cc-delete-btn" data-action="{{ route('cc.region.destroy_admin', $a) }}" data-username="{{ $a->username }}">Delete</button>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="5" class="text-muted">No RTO admins yet.</td></tr>
@endforelse
