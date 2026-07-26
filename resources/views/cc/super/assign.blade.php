@extends('layouts.cc')

@section('title', 'Assign Role')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">Assign Role: {{ $user->username }}</h1>
                    </div>
                    <a href="{{ route('cc.users.assign.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Back</a>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('cc.users.assign.store', $user) }}">
                    @csrf

                    <div class="form-group mb-3">
                        <label for="role">Role <span class="text-danger">*</span></label>
                        <select id="role" name="role" class="form-select" required onchange="toggleSegment(this.value)">
                            <option value="">— Select role —</option>
                            <option value="super" {{ old('role', $user->assignment === 'super' ? 'super' : '') === 'super' ? 'selected' : '' }}>
                                Super Admin
                            </option>
                            <option value="segment" {{ old('role', str_starts_with($user->assignment ?? '', 'segment_') ? 'segment' : '') === 'segment' ? 'selected' : '' }}>
                                Segment Admin
                            </option>
                        </select>
                    </div>

                    <div class="form-group mb-4" id="segment-box"
                        style="{{ old('role', str_starts_with($user->assignment ?? '', 'segment_') ? 'segment' : '') === 'segment' ? '' : 'display:none' }}">
                        <label for="segment">Segment <span class="text-danger">*</span></label>
                        <select id="segment" name="segment" class="form-select">
                            <option value="">— Select segment —</option>
                            @foreach($segments as $key => $label)
                                <option value="{{ $key }}"
                                    {{ old('segment', $user->assignment) === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning rounded-pill px-4">Save</button>
                        <a href="{{ route('cc.users.assign.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
function toggleSegment(role) {
    document.getElementById('segment-box').style.display = role === 'segment' ? '' : 'none';
}
</script>
@endsection
