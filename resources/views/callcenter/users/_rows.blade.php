@forelse($users as $user)
<tr>
    <td>{{ $user->username }}</td>
    <td>
        @if(optional($user->supervisorUser)->id)
            {{ optional($user->supervisorUser)->name ?: '' }} <span class="text-muted">({{ optional($user->supervisorUser)->username }})</span>
        @else
            —
        @endif
    </td>
    <td>{{ $user->admin_prev ? 'Call Center Admin' : 'Call Center User' }}</td>
    <td>
        @if($user->status)
        <span class="badge bg-success">Active</span>
        @else
        <span class="badge bg-secondary">Disabled</span>
        @endif
    </td>
    <td>{{ optional($user->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
    @php
        $isSupervisorView = \Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_');
        $currentSupervisorId = session('user')['id'] ?? null;
        $canEdit = ! $isSupervisorView || ($user->supervisor && $user->supervisor === $currentSupervisorId);
    @endphp
    <td class="text-end">
        <div class="d-inline-flex gap-1">
            @if($canEdit)
            <a href="{{ route('cc.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Edit</a>
            @endif
            @if($user->status)
            <button type="button" class="btn btn-sm btn-warning rounded-pill cc-disable-btn" data-action="{{ route('cc.users.disable', $user) }}" data-username="{{ $user->username }}">Disable</button>
            @else
            <button type="button" class="btn btn-sm btn-success rounded-pill cc-enable-btn" data-action="{{ route('cc.users.enable', $user) }}" data-username="{{ $user->username }}">Enable</button>
            @endif
            @if(!$user->fixed)
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill cc-delete-btn" data-action="{{ route('cc.users.destroy', $user) }}" data-username="{{ $user->username }}">Delete</button>
            @endif
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="text-center text-muted">No call center users yet.</td>
</tr>
@endforelse
