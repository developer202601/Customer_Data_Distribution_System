@extends('layouts.cc')

@section('title', 'Assign Role')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">System Administration</p>
                        <h1 class="process-upload-title mb-0">Assign Role for {{ $user->username }}</h1>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('rb.users.assign.store', $user) }}">
                    @csrf

                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select id="role" name="role" class="form-select" onchange="document.getElementById('region-field').style.display = this.value === 'region' ? '' : 'none'">
                            @foreach($roles as $key => $label)
                                <option value="{{ $key }}" {{ old('role', $user->assignment === 'super' ? 'super' : 'region') === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3" id="region-field" style="{{ old('role', $user->assignment === 'super' ? 'super' : 'region') === 'region' ? '' : 'display:none' }}">
                        <label for="region" class="form-label">Region</label>
                        <select id="region" name="region" class="form-select">
                            <option value="">-- Select a region --</option>
                            @foreach($regions as $region)
                                <option value="{{ $region }}" {{ old('region', $user->assignment) === $region ? 'selected' : '' }}>
                                    {{ $region }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning rounded-pill px-4">Save</button>
                        <a href="{{ route('rb.users.assign.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
