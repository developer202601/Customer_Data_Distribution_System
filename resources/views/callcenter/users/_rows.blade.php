@forelse($users as $user)
@php
    $assignment = $user->assignment ?? '';
    if ($assignment === 'super') {
        $role = 'Super Admin';
    } elseif (str_starts_with($assignment, 'REGION')) {
        $region = str_replace('REGION ', '', $assignment);
        $role = 'Region Admin for ' . $region;
    } elseif (str_starts_with($assignment, 'rtom_')) {
        $rtom = str_replace('rtom_', '', $assignment);
        $role = 'RTOM Admin for ' . $rtom;
    } elseif (str_starts_with($assignment, 'supervisor_rtom_')) {
        $rtom = str_replace('supervisor_rtom_', '', $assignment);
        $role = 'Supervisor for RTOM ' . $rtom;
    } elseif (str_starts_with($assignment, 'caller_rtom_')) {
        $rtom = str_replace('caller_rtom_', '', $assignment);
        $role = 'Caller for RTOM ' . $rtom;
    } else {
        $role = $user->admin_prev ? 'Call Center Admin' : 'Call Center User';
    }
    $currentUserId = session('user')['id'] ?? null;
    if ($user->supervisor && $user->supervisor == $currentUserId) {
        $supervisorInfo = 'Me';
    } else {
        $supervisorInfo = optional($user->supervisorUser)->name ? (optional($user->supervisorUser)->name . ' (' . optional($user->supervisorUser)->username . ')') : '—';
    }
@endphp
<tr class="user-row" title="Click to view more details" data-user-id="{{ $user->id }}" data-username="{{ $user->username }}" data-name="{{ $user->name }}" data-role="{{ $role }}" data-supervisor="{{ $supervisorInfo }}" data-status="{{ $user->status ? 'Active' : 'Disabled' }}" data-created="{{ optional($user->created_at)->format('Y-m-d H:i') ?? '—' }}">
    <td>{{ $user->username }}</td>
    <td>{{ $user->name ?: '—' }}</td>
    <td>{{ $role }}</td>
    <td>{{ $supervisorInfo }}</td>
    <td class="text-end">
        <div class="d-inline-flex gap-1">
            @if($user->status)
            <button type="button" class="btn btn-sm btn-warning rounded-pill cc-disable-btn" data-action="{{ route('cc.users.disable', $user) }}" data-username="{{ $user->username }}" onclick="event.stopPropagation()">Disable</button>
            @else
            <button type="button" class="btn btn-sm btn-success rounded-pill cc-enable-btn" data-action="{{ route('cc.users.enable', $user) }}" data-username="{{ $user->username }}" onclick="event.stopPropagation()">Enable</button>
            @endif
            @if(!$user->fixed)
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill cc-delete-btn" data-action="{{ route('cc.users.destroy', $user) }}" data-username="{{ $user->username }}" onclick="event.stopPropagation()">Delete</button>
            @endif
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="5" class="text-center text-muted">No call center users yet.</td>
</tr>
@endforelse
