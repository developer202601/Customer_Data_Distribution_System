@extends('layouts.cc')

@section('navbar-right')
<a href="{{ route('cc.dashboard') }}" class="btn btn-outline-secondary">Call Center Home</a>
<form action="{{ route('logout') }}" method="post" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-outline-secondary">Logout</button>
</form>
@endsection

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="card process-upload-card process-upload-card--transparent shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <p class="text-uppercase text-muted mb-1">Call Center — Region: {{ $region }}</p>
                        <h1 class="process-upload-title mb-0">RTOMs & RTOM Admins</h1>
                    </div>
                    <div class="d-flex gap-2">
                        
                        
                    </div>
                </div>

                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h6 mb-0">RTOM Admins</h2>
                        </div>

                        <div class="table-responsive cc-table-container">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Assignment</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rtomAdmins as $a)
                                    <tr>
                                        <td>{{ $a->username }}</td>
                                        <td><span class="badge bg-secondary">{{ $a->assignment }}</span></td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="{{ route('cc.region.edit_admin', $a) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                                @if($a->fixed)
                                                    <button class="btn btn-sm btn-warning" disabled>Fixed</button>
                                                @else
                                                    @if($a->status)
                                                        <form action="{{ route('cc.users.disable', $a) }}" method="post" onsubmit="return confirm('Disable this RTOM admin?');" class="d-inline">
                                                            @csrf
                                                            @method('put')
                                                            <input type="hidden" name="return_to" value="{{ route('cc.region.index') }}">
                                                            <button type="submit" class="btn btn-sm btn-warning">Disable</button>
                                                        </form>
                                                    @else
                                                        <form action="{{ route('cc.users.enable', $a) }}" method="post" onsubmit="return confirm('Enable this RTOM admin?');" class="d-inline">
                                                            @csrf
                                                            @method('put')
                                                            <input type="hidden" name="return_to" value="{{ route('cc.region.index') }}">
                                                            <button type="submit" class="btn btn-sm btn-success">Enable</button>
                                                        </form>
                                                    @endif
                                                    <form action="{{ route('cc.region.destroy_admin', $a) }}" method="post" onsubmit="return confirm('Delete this RTOM admin?');" class="d-inline">
                                                        @csrf
                                                        @method('delete')
                                                        <input type="hidden" name="return_to" value="{{ route('cc.region.index') }}">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="3" class="text-muted">No RTOM admins yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
