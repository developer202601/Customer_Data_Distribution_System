@forelse($users as $user)
@php
    $assignment = (string) ($user->assignment ?? '');
    if ($assignment === 'super') {
        $role = 'Super Admin';
    } elseif (str_starts_with($assignment, 'rtom_')) {
        $role = 'RTO Admin';
    } elseif (str_starts_with($assignment, 'caller_')) {
        $role = 'Caller';
    } else {
        $role = $assignment !== '' ? $assignment : 'User';
    }
@endphp
<tr>
    <td>{{ $user->username }}</td>
    <td>{{ $user->name ?: '—' }}</td>
    <td>{{ $assignment ?: '—' }}</td>
    <td>{{ $user->status ? 'Active' : 'Disabled' }}</td>
    <td class="text-end">
        <div class="d-inline-flex gap-1">
            <a href="{{ route('rb.users.edit', $user) }}" class="btn btn-sm btn-outline-primary rounded-pill">Edit</a>
            @if($user->status)
                <form method="post" action="{{ route('rb.users.disable', $user) }}" class="d-inline">
                    @csrf
                    @method('put')
                    <button type="submit" class="btn btn-sm btn-warning rounded-pill">Disable</button>
                </form>
            @else
                <form method="post" action="{{ route('rb.users.enable', $user) }}" class="d-inline">
                    @csrf
                    @method('put')
                    <button type="submit" class="btn btn-sm btn-success rounded-pill">Enable</button>
                </form>
            @endif
            <form method="post" action="{{ route('rb.users.destroy', $user) }}" class="d-inline">
                @csrf
                @method('delete')
                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">Delete</button>
            </form>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="5" class="text-center text-muted">No users found.</td>
</tr>
@endforelse
