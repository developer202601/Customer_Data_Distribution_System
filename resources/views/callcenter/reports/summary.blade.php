@extends('layouts.cc')

@section('content')
<div class="process-upload py-4">
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <p class="text-uppercase text-muted small mb-1">Past report</p>
                <h1 class="h4 mb-0">{{ $label }}</h1>
                <p class="small text-muted mb-0">Assigned by {{ $assigner }} · Captured {{ number_format($report->row_count) }} rows</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('cc.reports') }}" class="btn btn-outline-secondary btn-sm">Current report</a>
                <a href="{{ route('cc.reports.history') }}" class="btn btn-secondary btn-sm">Back to history</a>
            </div>
        </div>

        <div class="row g-3 mt-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-uppercase small text-muted mb-1">Assigned rows</p>
                        <h3 class="h5 mb-0">{{ number_format($assignedCount) }}</h3>
                        <p class="small text-muted mb-0">Accepted {{ number_format($acceptedCount) }} · {{ $acceptanceRate }}%</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-uppercase small text-muted mb-1">Interactions</p>
                        <h3 class="h5 mb-0">{{ number_format($totalInteractions) }}</h3>
                        @if($totalInteractions > 0)
                        <p class="small text-muted mb-0">Top caller: {{ data_get($topAgentByCalls, 'name', '—') }} ({{ number_format(data_get($topAgentByCalls, 'call_count', 0)) }} calls)</p>
                        @else
                        <p class="small text-muted mb-0">Awaiting the first accepted rows—no interactions have been logged yet.</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-uppercase small text-muted mb-1">Payments</p>
                        <h3 class="h5 mb-0 text-success">{{ number_format($totalPayments, 2) }}</h3>
                        @if($acceptedCount > 0)
                        <p class="small text-muted mb-0">Top earner: {{ data_get($topAgentByPayment, 'name', '—') }} @if(data_get($topAgentByPayment, 'payment_amount') !== null)· <span class="text-success">{{ number_format(data_get($topAgentByPayment, 'payment_amount', 0), 2) }}</span>@endif</p>
                        @else
                        <p class="small text-muted mb-0">Awaiting accepted rows before payments can be credited.</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-uppercase small text-muted mb-1">Customers</p>
                        <h3 class="h5 mb-0">{{ data_get($topAgentByCoverage, 'coverage', 0) }}%</h3>
                        @if($acceptedCount > 0)
                        <p class="small text-muted mb-0">All customers {{ number_format(data_get($topAgentByCoverage, 'accepted_rows', 0)) }}/{{ number_format(data_get($topAgentByCoverage, 'assigned_rows', 0)) }}</p>
                        <p class="small text-muted mb-0">Completed by {{ data_get($topAgentByCoverage, 'name', '—') }}</p>
                        @else
                        <p class="small text-muted mb-0">Coverage stats populate once agents accept their rows.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="h6 mb-0">Agent performance</h5>
                            <span class="small text-muted">Sorted by payments</span>
                        </div>
                        @if($agentMetrics->isEmpty())
                        <p class="text-center text-muted small mb-0">No agents logged interactions yet.</p>
                        @else
                        <div class="cc-agent-scroll">
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0">
                                    <thead>
                                        <tr>
                                            <th>Agent</th>
                                            <th>Assigned</th>
                                            <th>Calls</th>
                                            <th>Payments</th>
                                            <th>Coverage / All</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($agentMetrics as $agent)
                                        <tr>
                                            <td class="small">
                                                <strong>{{ $agent['name'] }}</strong>
                                                <div class="text-muted">Accepted {{ number_format($agent['accepted_rows']) }} / {{ number_format($agent['assigned_rows']) }}</div>
                                            </td>
                                            <td>
                                                <span class="text-muted small">{{ number_format($agent['assigned_rows']) }} rows</span>
                                            </td>
                                            <td>
                                                <strong>{{ number_format($agent['call_count']) }}</strong>
                                            </td>
                                            <td>
                                                <strong>{{ number_format($agent['payment_amount'], 2) }}</strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">{{ $agent['coverage'] }}%</span>
                                                <div class="text-muted small">{{ number_format($agent['accepted_rows']) }}/{{ number_format($agent['assigned_rows']) }}</div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="h6">Call center leaders</h5>
                        @if($acceptedCount > 0)
                        <div class="d-flex flex-column gap-3 mt-3">
                            <div>
                                <p class="text-uppercase small text-muted mb-1">Best revenue</p>
                                <strong>{{ data_get($topAgentByPayment, 'name', '—') }}</strong>
                                <div class="text-muted small">{{ number_format(data_get($topAgentByPayment, 'payment_amount', 0), 2) }}</div>
                            </div>
                            <div>
                                <p class="text-uppercase small text-muted mb-1">Best coverage</p>
                                <strong>{{ data_get($topAgentByCoverage, 'name', '—') }}</strong>
                                <div class="text-muted small">{{ data_get($topAgentByCoverage, 'coverage', 0) }}%</div>
                            </div>
                            <div>
                                <p class="text-uppercase small text-muted mb-1">Most calls</p>
                                <strong>{{ data_get($topAgentByCalls, 'name', '—') }}</strong>
                                <div class="text-muted small">{{ number_format(data_get($topAgentByCalls, 'call_count', 0)) }} calls</div>
                            </div>
                        </div>
                        @else
                        <p class="text-muted small mb-0 mt-3">No accepted rows yet; leaderboards appear once agents confirm their assignments.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <p class="text-uppercase small text-muted mb-0">Calls per day</p>
                            <span class="small text-muted">{{ $callsCalendar['startLabel'] }} – {{ $callsCalendar['endLabel'] }}</span>
                        </div>
                        @php $weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; @endphp
                        <div class="cc-daily-calendar mt-3">
                            <div class="cc-calendar-weekdays">
                                @foreach($weekdays as $day)
                                <span>{{ $day }}</span>
                                @endforeach
                            </div>
                            <div class="cc-calendar-grid">
                                @foreach($callsCalendar['weeks'] as $week)
                                @foreach($week as $day)
                                @php
                                $dateKey = $day['date']->toDateString();
                                $tooltip = $dailyBestAgents[$dateKey] ?? null;
                                @endphp
                                <div class="cc-calendar-cell {{ $day['isStartDate'] ? 'cc-calendar-cell--start' : '' }} {{ empty($day['in_range']) ? 'cc-calendar-cell--disabled' : '' }}" title="{{ $day['date']->format('M j, Y') }} · {{ $day['count'] }} calls">
                                    <div class="cc-calendar-date">{{ $day['date']->format('j') }}</div>
                                    <div class="cc-calendar-count {{ $day['count'] ? '' : 'cc-calendar-count--empty' }}">
                                        @if($day['count'])
                                        {{ $day['count'] }}
                                        @endif
                                    </div>
                                    @if($tooltip && !empty($day['in_range']))
                                    <div class="cc-calendar-tooltip">
                                        <strong>{{ $tooltip['name'] }}</strong>
                                        <span>{{ number_format($tooltip['calls']) }} calls</span>
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="text-uppercase small text-muted mb-1">Calls per week</p>
                        <p class="small text-muted mb-3">Weeks from {{ $callsCalendar['startLabel'] }} through {{ $callsCalendar['endLabel'] }}</p>
                        <ul class="list-unstyled mb-0">
                            @foreach($callsPerWeek as $slice)
                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span class="small text-muted">{{ $slice['label'] }}</span>
                                <strong>{{ number_format($slice['count']) }}</strong>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="h6 mb-0">Assignments not accepted</h5>
                            <span class="small text-muted">Rows assigned by the admin but still pending or rejected by the agent</span>
                        </div>
                        @if($assignedCount == 0)
                        <p class="mb-0 text-muted small">No rows have been assigned for this report.</p>
                        @elseif($nonAcceptedAssignments->isEmpty())
                        <p class="mb-0 text-muted small">Every assigned row was accepted.</p>
                        @else
                        @php
                        $formatAgentLabel = function ($agent) {
                        $name = trim((string) ($agent?->name ?? ''));
                        $username = trim((string) ($agent?->username ?? ''));
                        if ($name && $username) {
                        return $name.' ('.$username.')';
                        }
                        if ($name) {
                        return $name;
                        }
                        if ($username) {
                        return $username;
                        }
                        return 'Unassigned';
                        };

                        // Group per agent and produce a single status row: prefer 'rejected' if any rejected rows exist
                        $grouped = $nonAcceptedAssignments->groupBy('assigned_user_id');
                        $agentSummaries = $grouped->map(function ($items) use ($formatAgentLabel) {
                        $agent = $items->first()->agent ?? null;
                        $label = $formatAgentLabel($agent);
                        $pendingItems = $items->where('rejected', false);
                        $rejectedItems = $items->where('rejected', true);

                        if ($rejectedItems->count() > 0) {
                        $reasons = $rejectedItems->pluck('rejection_note')->filter(fn($r) => trim((string)$r) !== '');
                        $uniqueReasons = $reasons->unique()->values();
                        $latest = $rejectedItems->sortByDesc(function($i){ return $i->rejected_at ?? $i->updated_at ?? $i->created_at; })->first();
                        $primaryReason = trim((string)($latest->rejection_note ?? ''));
                        $latestRejectedAt = $latest && ($latest->rejected_at ?? null)
                        ? optional($latest->rejected_at)->format('M j, Y')
                        : (optional($latest->updated_at)->format('M j, Y') ?? optional($latest->created_at)->format('M j, Y'));

                        if ($uniqueReasons->count() === 0) {
                        $reasonText = 'No reason provided';
                        } elseif ($uniqueReasons->count() === 1) {
                        $reasonText = $uniqueReasons->first();
                        } else {
                        $reasonText = ($primaryReason !== '' ? $primaryReason : $uniqueReasons->first()) . ' (+' . ($uniqueReasons->count() - 1) . ' more)';
                        }

                        return [
                        'label' => $label,
                        'status' => 'rejected',
                        'count' => $rejectedItems->count(),
                        'reason' => $reasonText,
                        'pending_since' => null,
                        'rejected_at' => $latestRejectedAt,
                        ];
                        }

                        $earliestPending = $pendingItems->sortBy(function($i){ return $i->created_at ?? $i->updated_at; })->first();
                        $pendingSince = $earliestPending
                        ? (optional($earliestPending->created_at)->format('M j, Y') ?? optional($earliestPending->updated_at)->format('M j, Y'))
                        : null;

                        return [
                        'label' => $label,
                        'status' => 'pending',
                        'count' => $pendingItems->count(),
                        'reason' => null,
                        'pending_since' => $pendingSince,
                        'rejected_at' => null,
                        ];
                        })->values();
                        @endphp
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Agent</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                        <th>Timeline</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($agentSummaries as $summary)
                                    <tr>
                                        <td>{{ $summary['label'] }}</td>
                                        <td>
                                            @if($summary['status'] === 'rejected')
                                            <span class="badge bg-warning text-dark small">Rejected ({{ number_format($summary['count']) }})</span>
                                            @else
                                            <span class="badge bg-secondary text-dark small">Pending ({{ number_format($summary['count']) }})</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($summary['status'] === 'rejected')
                                            {{ $summary['reason'] }}
                                            @else
                                            <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                            $pendingSince = $summary['pending_since'] ?? null;
                                            $rejectedAt = $summary['rejected_at'] ?? null;
                                            @endphp
                                            @if($pendingSince && $rejectedAt)
                                            <span class="small">Pending since {{ $pendingSince }} · Rejected at {{ $rejectedAt }}</span>
                                            @elseif($rejectedAt)
                                            <span class="small">Rejected at {{ $rejectedAt }}</span>
                                            @elseif($pendingSince)
                                            <span class="small">Pending since {{ $pendingSince }}</span>
                                            @else
                                            <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card ul li:first-child {
        border-top: none;
    }

    .cc-agent-scroll {
        max-height: 320px;
        overflow-y: auto;
    }

    .cc-agent-scroll table {
        margin-bottom: 0;
    }

    .cc-daily-calendar {
        border-radius: 0.85rem;
        background: #f8f9fa;
        padding: 0.75rem;
    }

    .cc-calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.35rem;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.4rem;
        color: #6c757d;
    }

    .cc-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.35rem;
    }

    .cc-calendar-cell {
        background: #fff;
        border-radius: 0.75rem;
        padding: 0.5rem;
        min-height: 70px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border: 1px solid rgba(0, 0, 0, 0.05);
        position: relative;
    }

    .cc-calendar-cell--start {
        border-color: #0d6efd;
        box-shadow: 0 0 0 1px rgba(13, 110, 253, 0.3);
    }

    .cc-calendar-date {
        font-weight: 600;
    }

    .cc-calendar-count {
        font-size: 0.9rem;
        color: #0d6efd;
        text-align: right;
        min-height: 1rem;
    }

    .cc-calendar-count--empty {
        opacity: 0;
        pointer-events: none;
        min-height: 1rem;
    }

    .cc-calendar-tooltip {
        position: absolute;
        left: 50%;
        top: 0;
        transform: translate(-50%, -110%);
        background: #fff;
        padding: 0.35rem 0.75rem;
        border-radius: 0.6rem;
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        font-size: 0.75rem;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.15s ease, visibility 0.15s ease;
        white-space: nowrap;
        pointer-events: none;
        text-align: center;
    }

    .cc-calendar-tooltip strong {
        display: block;
        font-size: 0.8rem;
    }

    .cc-calendar-cell:hover .cc-calendar-tooltip {
        opacity: 1;
        visibility: visible;
    }

    .cc-calendar-cell--disabled {
        opacity: 0.55;
        background: #f1f3f5;
        color: #6c757d;
        pointer-events: none;
    }

    .cc-calendar-cell--disabled .cc-calendar-tooltip {
        display: none;
    }
</style>
@endpush