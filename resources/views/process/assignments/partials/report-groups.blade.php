@php use Illuminate\Support\Str; @endphp

<div class="mt-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1">Past generated reports</h2>
            <small class="text-muted">Click a report to re-open its assignments overview.</small>
        </div>
        <a href="{{ route('process.assignments.reports') }}" class="btn btn-sm btn-outline-secondary" data-loader-off="1">View full archive</a>
    </div>

    @if($reportGroups->isEmpty())
    <div class="alert alert-secondary mb-0" role="status">
        No exported reports available yet.
    </div>
    @else
    <div class="accordion" id="report-accordion">
        @foreach($reportGroups as $month => $processes)
        <div class="accordion-item mb-3">
            <h2 class="accordion-header" id="heading-{{ Str::slug($month) }}">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ Str::slug($month) }}" aria-expanded="false" aria-controls="collapse-{{ Str::slug($month) }}">
                    {{ $month }}
                </button>
            </h2>
            <div id="collapse-{{ Str::slug($month) }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ Str::slug($month) }}" data-bs-parent="#report-accordion">
                <div class="accordion-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($processes as $processRow)
                        <div class="list-group-item d-flex justify-content-between align-items-start flex-column flex-sm-row gap-3">
                            <div>
                                <div class="fw-semibold">{{ $processRow->dataset_month ?? $processRow->run_date?->format('M Y') ?? 'Unknown report' }}</div>
                                <div class="text-muted small">
                                    Generated {{ $processRow->run_date?->format('d M Y – H:i') ?? $processRow->created_at->format('d M Y – H:i') }} · {{ $processRow->token }}
                                </div>
                                @if($processRow->exports->isNotEmpty())
                                <div class="mt-1 small text-muted">
                                    {{ $processRow->exports->pluck('label')->unique()->implode(', ') }}
                                </div>
                                @endif
                            </div>
                            <div class="text-end">
                                <a href="{{ route('process.assignments.report', ['process' => $processRow]) }}" class="btn btn-outline-primary" data-loader-off="1">
                                    View assignments
                                </a>
                                <p class="text-muted small mb-0">{{ $processRow->exports->count() }} report files</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
