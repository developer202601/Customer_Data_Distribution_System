@forelse($callers as $user)
<tr>
    <td>{{ $user->username }}</td>
    <td>{{ $user->name ?? '-' }}</td>
    <td>
        @if($user->status)
            <span class="badge bg-success">Active</span>
        @else
            <span class="badge bg-secondary">Disabled</span>
        @endif
    </td>
    <td class="text-end">
        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('cc.region.callers.edit', $user) }}" class="btn btn-sm btn-outline-primary rounded-pill">Edit</a>
            @if($user->status)
                <button type="button" class="btn btn-sm btn-warning rounded-pill cc-disable-btn" data-action="{{ route('cc.region.callers.disable', $user) }}" data-username="{{ $user->username }}">Disable</button>
            @else
                <button type="button" class="btn btn-sm btn-success rounded-pill cc-enable-btn" data-action="{{ route('cc.region.callers.enable', $user) }}" data-username="{{ $user->username }}">Enable</button>
            @endif
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill cc-delete-btn" data-action="{{ route('cc.region.callers.destroy', $user) }}" data-username="{{ $user->username }}">Delete</button>
        </div>
    </td>
</tr>
@empty
<tr><td colspan="4" class="text-muted">No callers found.</td></tr>
@endforelse
