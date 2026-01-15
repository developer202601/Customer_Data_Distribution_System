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
                        <h1 class="process-upload-title mb-0">Region Dashboard</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('cc.region.index') }}" class="btn btn-outline-success rounded-pill px-4">RTOMs & Admins</a>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card p-3">
                            <div class="small text-muted">Total Assignments</div>
                            <div class="h4 mb-0">{{ $total }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3">
                            <div class="small text-muted">Assigned</div>
                            <div class="h4 mb-0">{{ $assigned }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3">
                            <div class="small text-muted">Unassigned</div>
                            <div class="h4 mb-0">{{ $unassigned }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3">
                            <div class="small text-muted">Paid (count)</div>
                            <div class="h4 mb-0">{{ $paidCount }}</div>
                        </div>
                    </div>
                </div>

                <div class="card p-3 mb-4">
                    <h6 class="mb-3">RTOM Breakdown</h6>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>RTOM</th>
                                    <th>Total</th>
                                    <th>Assigned</th>
                                    <th>Paid</th>
                                    <th class="text-end">Paid Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rtomBreakdown as $r)
                                <tr>
                                    <td>{{ $r['rtom'] }}</td>
                                    <td>{{ $r['total'] }}</td>
                                    <td>{{ $r['assigned'] }}</td>
                                    <td>{{ $r['paid'] }}</td>
                                    <td class="text-end">{{ number_format($r['paid_amount'], 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-muted">No data available for this region.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
