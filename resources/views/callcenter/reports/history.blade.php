@extends('layouts.cc')

@section('content')
    <div class="process-upload py-4">
        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <p class="text-uppercase text-muted small mb-1">Call center</p>
                    <h1 class="h4 mb-0">Report archive</h1>
                    <p class="small text-muted mb-0">Browse every call center dataset and see who worked each report, how many calls they logged, and what paid off.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('cc.reports') }}" class="btn btn-outline-secondary btn-sm">Active report</a>
                </div>
            </div>

            @php
                $historySummary = collect($history);
                $totalCalls = $historySummary->sum('interactions');
                $avgAcceptance = $historySummary->count() ? round($historySummary->avg('acceptance_rate'), 1) : 0;
            @endphp

            <div class="row g-3 mt-4">
                <div class="col-sm-6 col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-uppercase small text-muted mb-1">Payments</p>
                            <h3 class="h5 mb-0">{{ number_format($yearPayments, 2) }}</h3>
                            <p class="small text-muted mb-0">Total paid this year</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-uppercase small text-muted mb-1">Calls</p>
                            <h3 class="h5 mb-0">{{ number_format($totalCalls) }}</h3>
                            <p class="small text-muted mb-0">Interactions recorded</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-uppercase small text-muted mb-1">Acceptance</p>
                            <h3 class="h5 mb-0">{{ $avgAcceptance }}%</h3>
                            <p class="small text-muted mb-0">Average across reports</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-4">
                @forelse($history as $report)
                    <div class="col-12 col-lg-6">
                        <a href="{{ route('cc.reports.summary', $report['id']) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <p class="text-uppercase small text-muted mb-1">{{ $report['assigner'] }}</p>
                                        <h3 class="h5 mb-1">{{ $report['label'] }}</h3>
                                        <p class="text-muted small mb-0">Dataset rows: {{ number_format($report['row_count']) }}</p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary">{{ number_format($report['interactions']) }} calls</span>
                                        <div class="text-muted small">{{ $report['created_at']->format('M j, Y') }}</div>
                                    </div>
                                </div>

                                <div class="row mt-3 g-2">
                                    <div class="col-6 col-sm-4">
                                        <div class="small text-uppercase text-muted">Assigned</div>
                                        <strong>{{ number_format($report['assigned_rows']) }}</strong>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="small text-uppercase text-muted">Accepted</div>
                                        <strong>{{ number_format($report['accepted_rows']) }}</strong>
                                    </div>
                                    <div class="col-6 col-sm-4">
                                        <div class="small text-uppercase text-muted">Rejected</div>
                                        <strong>{{ number_format($report['rejected_rows']) }}</strong>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                    <div>
                                        <div class="small text-muted">Payments</div>
                                        <strong>{{ number_format($report['payment_amount'], 2) }}</strong>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-muted">Acceptance</div>
                                        <strong>{{ $report['acceptance_rate'] }}%</strong>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card border-0 shadow-sm text-center">
                            <div class="card-body text-muted small">No archived reports yet.</div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .card.text-decoration-none:hover {
        transform: translateY(-2px);
        transition: transform 0.2s ease;
    }
</style>
@endpush
