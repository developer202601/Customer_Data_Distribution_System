@extends('layouts.cc')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Edit RTO Admin</p>
                        <h1 class="process-upload-title mb-0">{{ $user->username }}</h1>
                    </div>
                </div>

                <form action="{{ route('cc.region.update_admin', $user) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">RTO</label>
                        <select name="rtom" class="form-select">
                            @foreach($rtoms as $r)
                                @php
                                    $currentKey = str_replace('rtom_', '', (string) $user->assignment);
                                    $oldKey = old('rtom') !== null
                                        ? preg_replace('/\s+/', '_', strtolower((string) old('rtom')))
                                        : null;
                                    $optionKey = preg_replace('/\s+/', '_', strtolower((string) $r));
                                @endphp
                                <option value="{{ $r }}" {{ ($oldKey ?? $currentKey) === $optionKey ? 'selected' : '' }}>{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Save</button>
                        <a href="{{ route('cc.region.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    </div>
                </form>

                {{-- Delete action removed: deletion handled from RTOM list only --}}

            </div>
        </div>
    </div>
</div>
@endsection
