@extends('layouts.cc')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">{{ $isSupervisor ? 'Create Supervisor Admin' : 'Create RTOM Admin' }}</p>
                        <h1 class="process-upload-title mb-0">{{ $isSupervisor ? 'New Supervisor Admin' : 'New RTOM Admin' }}</h1>
                    </div>
                </div>

                @php $action = route('cc.region.store_admin'); @endphp
                @include('cc.partials._user_create_form', ['mode' => 'rtom', 'action' => $action, 'rtoms' => $rtoms, 'isSupervisor' => $isSupervisor ?? false])

            </div>
        </div>
    </div>
</div>
@endsection
