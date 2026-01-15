@extends('layouts.cc')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center Administration</p>
                        <h1 class="process-upload-title mb-0">Create New Region User</h1>
                        <p class="text-muted small mb-0">Creates a region admin user (Region Admin) and assigns them to a region from the last two reports.</p>
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

                @php
                    $action = route('cc.users.create.store');
                @endphp
                @include('cc.partials._user_create_form', ['mode' => 'region', 'action' => $action, 'regions' => $regions])
        </div>
    </div>
</div>
@endsection

<!-- region script provided by partial when mode=region -->