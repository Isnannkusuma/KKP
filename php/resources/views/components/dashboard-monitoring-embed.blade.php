@props([
    'period' => 30,
    'stats' => ['total' => 0, 'answered' => 0, 'unanswered' => 0],
    'daily' => [],
])

@php
    $totalQuestions = (int) ($stats['total'] ?? 0);
    $answeredQuestions = (int) ($stats['answered'] ?? 0);
    $unansweredQuestions = (int) ($stats['unanswered'] ?? 0);
    $resolutionRate = $totalQuestions > 0 ? (int) round(($answeredQuestions / $totalQuestions) * 100) : 0;
    $unansweredRate = $totalQuestions > 0 ? (int) round(($unansweredQuestions / $totalQuestions) * 100) : 0;
    $maximumActivity = max(1, collect($daily)->max('total') ?? 0);
    $dayCount = max(1, count($daily));
@endphp

<section class="monitoring-dashboard" aria-labelledby="monitoring-dashboard-title">
    <header class="monitoring-header">
        <div>
            <p class="monitoring-status"><span aria-hidden="true"></span>Sistem aktif &middot; Data real-time</p>
            <h1 id="monitoring-dashboard-title">Monitoring Chatbot</h1>
            <p class="monitoring-subtitle">Analisis penggunaan dan resolusi jawaban.</p>
        </div>

        <div class="monitoring-controls">
            <form method="GET" action="{{ url()->current() }}">
                <label class="sr-only" for="monitoring-period">Periode data</label>
                <select id="monitoring-period" name="period" onchange="this.form.submit()">
                    <option value="7" @selected((int) $period === 7)>7 hari terakhir</option>
                    <option value="30" @selected((int) $period === 30)>30 hari terakhir</option>
                    <option value="90" @selected((int) $period === 90)>90 hari terakhir</option>
                </select>
            </form>
            <button class="monitoring-refresh" type="button" onclick="window.location.reload()" aria-label="Muat ulang data" title="Muat ulang data">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M20 7v5h-5M4 17v-5h5m-3.1-3A7 7 0 0 1 18 7l2 5M4 12l2 5a7 7 0 0 0 12.1-2" />
                </svg>
            </button>
        </div>
    </header>

    <div class="monitoring-metrics">
        <article class="monitoring-card monitoring-metric">
            <div>
                <p class="monitoring-label">Total pertanyaan</p>
                <p class="monitoring-value">{{ $totalQuestions }}</p>
                <p class="monitoring-detail">Dalam {{ $period }} hari terakhir</p>
            </div>
            <span class="monitoring-icon monitoring-icon-info" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M8 10h8m-8 4h5m7-7v10a2 2 0 0 1-2 2h-4l-4 3v-3H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2Zm-3-4h3a2 2 0 0 1 2 2v3" /></svg>
            </span>
        </article>

        <article class="monitoring-card monitoring-metric">
            <div>
                <p class="monitoring-label">Tingkat resolusi</p>
                <p class="monitoring-value">{{ $resolutionRate }}%</p>
                <p class="monitoring-detail">{{ $answeredQuestions }} pertanyaan terjawab</p>
            </div>
            <span class="monitoring-icon monitoring-icon-success" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" /><path d="m8.5 12 2.3 2.3 4.7-4.8" /></svg>
            </span>
        </article>

        <article class="monitoring-card monitoring-metric">
            <div>
                <p class="monitoring-label">Pertanyaan belum terjawab</p>
                <p class="monitoring-value">{{ $unansweredQuestions }}</p>
                <p class="monitoring-detail">Belum ditemukan jawaban relevan</p>
            </div>
            <span class="monitoring-icon monitoring-icon-warning" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M5 4h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-7l-5 3v-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" /></svg>
            </span>
        </article>
    </div>

    <div class="monitoring-charts">
        <article class="monitoring-card monitoring-chart-card">
            <div class="monitoring-chart-heading">
                <h2>Aktivitas pertanyaan</h2>
                <p>Volume pertanyaan pengguna per hari.</p>
                <div class="monitoring-chart-ratios" aria-label="Rasio status pertanyaan">
                    <span class="monitoring-chart-ratio"><i class="monitoring-legend-answered"></i>Terjawab {{ $resolutionRate }}%</span>
                    <span class="monitoring-chart-ratio"><i class="monitoring-legend-unanswered"></i>Belum terjawab {{ $unansweredRate }}%</span>
                </div>
            </div>
            <div class="monitoring-chart" aria-label="Grafik volume pertanyaan pengguna per hari">
                <div class="monitoring-plot" style="--monitoring-day-count: {{ $dayCount }}">
                    @forelse ($daily as $index => $item)
                        @php
                            $total = (int) ($item['total'] ?? 0);
                            $answered = (int) ($item['answered'] ?? 0);
                            $unanswered = (int) ($item['unanswered'] ?? 0);
                            $activityHeight = $total > 0 ? max(3, ($total / $maximumActivity) * 100) : 0;
                            $dayLabel = $item['label'] ?? $item['date'] ?? '';
                        @endphp
                        <button
                            class="monitoring-day-hit"
                            type="button"
                            title="{{ $dayLabel }}: {{ $total }} pertanyaan, {{ $answered }} terjawab, {{ $unanswered }} belum terjawab"
                            aria-label="{{ $dayLabel }}: {{ $total }} pertanyaan, {{ $answered }} terjawab, {{ $unanswered }} belum terjawab. Klik untuk melihat rincian."
                            data-day="{{ $dayLabel }}"
                            data-total="{{ $total }}"
                            data-answered="{{ $answered }}"
                            data-unanswered="{{ $unanswered }}"
                        >
                            <span class="monitoring-activity-bar" aria-hidden="true" style="height: {{ $activityHeight }}%"></span>
                        </button>
                    @empty
                        <p class="monitoring-empty">Belum ada data pada periode ini.</p>
                    @endforelse
                </div>
                <p class="monitoring-day-detail" role="status" aria-live="polite" hidden></p>
                @if (count($daily) > 0)
                    <div class="monitoring-axis" style="--monitoring-day-count: {{ $dayCount }}">
                        @foreach ($daily as $index => $item)
                            <span>
                                @if ($index % 5 === 0 || $index === count($daily) - 1)
                                    {{ $item['label'] ?? '' }}
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </article>

        <article class="monitoring-card monitoring-chart-card">
            <div class="monitoring-chart-heading">
                <h2>Resolusi per hari</h2>
                <p>Perbandingan jawaban berhasil dan yang belum terjawab.</p>
            </div>
            <div class="monitoring-chart" aria-label="Grafik perbandingan jawaban berhasil dan belum terjawab per hari">
                <div class="monitoring-plot monitoring-resolution-plot" style="--monitoring-day-count: {{ $dayCount }}">
                    @forelse ($daily as $item)
                        @php
                            $answered = (int) ($item['answered'] ?? 0);
                            $unanswered = (int) ($item['unanswered'] ?? 0);
                            $answeredHeight = $answered > 0 ? max(3, ($answered / $maximumActivity) * 100) : 0;
                            $unansweredHeight = $unanswered > 0 ? max(3, ($unanswered / $maximumActivity) * 100) : 0;
                            $dayLabel = $item['label'] ?? $item['date'] ?? '';
                            $total = (int) ($item['total'] ?? ($answered + $unanswered));
                        @endphp
                        <button
                            class="monitoring-day-hit"
                            type="button"
                            title="{{ $dayLabel }}: {{ $total }} pertanyaan, {{ $answered }} terjawab, {{ $unanswered }} belum terjawab"
                            aria-label="{{ $dayLabel }}: {{ $total }} pertanyaan, {{ $answered }} terjawab, {{ $unanswered }} belum terjawab. Klik untuk melihat rincian."
                            data-day="{{ $dayLabel }}"
                            data-total="{{ $total }}"
                            data-answered="{{ $answered }}"
                            data-unanswered="{{ $unanswered }}"
                        >
                            <span class="monitoring-resolution-bars" aria-hidden="true">
                                <span class="monitoring-answered-bar" style="height: {{ $answeredHeight }}%"></span>
                                <span class="monitoring-unanswered-bar" style="height: {{ $unansweredHeight }}%"></span>
                            </span>
                        </button>
                    @empty
                        <p class="monitoring-empty">Belum ada data pada periode ini.</p>
                    @endforelse
                </div>
                <p class="monitoring-day-detail" role="status" aria-live="polite" hidden></p>
                <div class="monitoring-legend">
                    <span><i class="monitoring-legend-answered"></i>Terjawab: {{ $answeredQuestions }}</span>
                    <span><i class="monitoring-legend-unanswered"></i>Belum terjawab: {{ $unansweredQuestions }}</span>
                </div>
            </div>
        </article>
    </div>
</section>

<style>
    .monitoring-dashboard {
        --monitoring-teal: #078b83;
        --monitoring-bar: #53b7a5;
        --monitoring-ink: #172033;
        --monitoring-muted: #607596;
        box-sizing: border-box;
        display: grid;
        gap: 26px;
        padding: 24px 28px;
        color: var(--monitoring-ink);
        background: transparent;
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .monitoring-dashboard *,
    .monitoring-dashboard *::before,
    .monitoring-dashboard *::after {
        box-sizing: border-box;
    }

    .monitoring-header,
    .monitoring-card {
        border: 1px solid #d5e5e7;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.94);
        box-shadow: 0 8px 26px rgba(32, 75, 82, 0.06);
    }

    .monitoring-header {
        display: flex;
        min-height: 122px;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 20px 22px;
    }

    .monitoring-status {
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 0 0 8px;
        color: #00a879;
        font-size: 13px;
    }

    .monitoring-status span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #00be83;
    }

    .monitoring-header h1 {
        margin: 0;
        font-size: 24px;
        font-weight: 750;
        letter-spacing: -0.04em;
        line-height: 1.25;
    }

    .monitoring-subtitle,
    .monitoring-chart-heading p {
        margin: 4px 0 0;
        color: var(--monitoring-muted);
        font-size: 14px;
    }

    .monitoring-controls {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 9px;
    }

    .monitoring-controls select,
    .monitoring-refresh {
        height: 42px;
        border: 1px solid #d8e5e8;
        border-radius: 999px;
        background: #fff;
        color: #34445c;
        box-shadow: 0 2px 4px rgba(27, 55, 66, 0.1);
    }

    .monitoring-controls select {
        padding: 0 14px;
        font: inherit;
        font-size: 14px;
    }

    .monitoring-refresh {
        display: grid;
        width: 42px;
        place-items: center;
        cursor: pointer;
        color: var(--monitoring-teal);
    }

    .monitoring-refresh svg,
    .monitoring-icon svg {
        width: 20px;
        height: 20px;
        stroke: currentColor;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-width: 1.8;
    }

    .monitoring-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .monitoring-metric {
        display: flex;
        min-height: 174px;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 26px;
    }

    .monitoring-label {
        margin: 0;
        color: var(--monitoring-muted);
        font-size: 14px;
    }

    .monitoring-value {
        margin: 12px 0 4px;
        font-size: 32px;
        font-weight: 750;
        letter-spacing: -0.035em;
        line-height: 1;
    }

    .monitoring-detail {
        margin: 8px 0 0;
        color: #8ca0bd;
        font-size: 12px;
    }

    .monitoring-icon {
        display: grid;
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        place-items: center;
        border-radius: 13px;
    }

    .monitoring-icon-info {
        background: #eaf5f3;
        color: var(--monitoring-teal);
    }

    .monitoring-icon-success {
        background: #eafbf3;
        color: #00a879;
    }

    .monitoring-icon-warning {
        background: #fff9e8;
        color: #ed8500;
    }

    .monitoring-charts {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: stretch;
        gap: 26px;
    }

    .monitoring-chart-card {
        min-width: 0;
        padding: 24px 26px 20px;
    }

    .monitoring-chart-heading h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 650;
    }

    .monitoring-chart-ratios {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }

    .monitoring-chart-ratio {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 9px;
        border: 1px solid #e2eceb;
        border-radius: 999px;
        background: #fff;
        color: var(--monitoring-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .monitoring-chart-ratio i {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .monitoring-chart {
        margin-top: 30px;
    }

    .monitoring-plot {
        display: grid;
        height: 224px;
        grid-template-columns: repeat(var(--monitoring-day-count), minmax(0, 1fr));
        align-items: stretch;
        gap: 5px;
        overflow: visible;
        border-bottom: 1px solid #d5e5e7;
        border-left: 1px solid #d5e5e7;
        padding: 0 5px;
    }

    .monitoring-day-hit {
        display: flex;
        width: 100%;
        min-width: 0;
        height: 100%;
        align-items: end;
        justify-content: center;
        padding: 0;
        border: 0;
        background: transparent;
        cursor: pointer;
    }

    .monitoring-day-hit:focus-visible {
        outline: 2px solid var(--monitoring-teal);
        outline-offset: 2px;
    }

    .monitoring-activity-bar,
    .monitoring-answered-bar,
    .monitoring-unanswered-bar {
        display: block;
        min-width: 2px;
        border-radius: 6px 6px 0 0;
    }

    .monitoring-activity-bar,
    .monitoring-answered-bar {
        background: var(--monitoring-bar);
    }

    .monitoring-activity-bar {
        width: 100%;
    }

    .monitoring-resolution-bars {
        display: flex;
        width: 100%;
        height: 100%;
        align-items: end;
        justify-content: center;
        gap: 2px;
    }

    .monitoring-resolution-bars span {
        width: min(calc(50% - 1px), 12px);
    }

    .monitoring-unanswered-bar {
        background: #ff9a00;
    }

    .monitoring-day-detail {
        margin: 10px 0 0;
        padding: 8px 10px;
        border: 1px solid #d5e5e7;
        border-radius: 9px;
        background: #f7fbfa;
        color: var(--monitoring-muted);
        font-size: 12px;
    }

    .monitoring-day-detail[hidden] {
        display: none;
    }

    .monitoring-axis {
        display: grid;
        grid-template-columns: repeat(var(--monitoring-day-count), minmax(0, 1fr));
        gap: 5px;
        padding-top: 9px;
        color: #879bb7;
        font-size: 12px;
    }

    .monitoring-axis span {
        white-space: nowrap;
    }

    .monitoring-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        padding-top: 13px;
        color: var(--monitoring-muted);
        font-size: 12px;
    }

    .monitoring-legend span {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .monitoring-legend i {
        width: 9px;
        height: 9px;
        border-radius: 50%;
    }

    .monitoring-legend-answered {
        background: var(--monitoring-teal);
    }

    .monitoring-legend-unanswered {
        background: #ff9a00;
    }

    .monitoring-empty {
        grid-column: 1 / -1;
        align-self: center;
        margin: 0;
        color: var(--monitoring-muted);
        font-size: 13px;
        text-align: center;
    }

    .monitoring-dashboard .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        clip-path: inset(50%);
    }

    @media (max-width: 900px) {
        .monitoring-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .monitoring-charts {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .monitoring-dashboard {
            gap: 16px;
            padding: 14px;
        }

        .monitoring-header,
        .monitoring-metric,
        .monitoring-chart-card {
            padding: 18px;
        }

        .monitoring-metrics {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .monitoring-metric {
            min-height: 138px;
        }

        .monitoring-controls {
            width: 100%;
        }

        .monitoring-chart-card {
            overflow: hidden;
        }

        .monitoring-plot {
            gap: 2px;
        }
    }
</style>

<script>
    document.querySelectorAll('.monitoring-dashboard:not([data-monitoring-interactive])').forEach((dashboard) => {
        dashboard.dataset.monitoringInteractive = 'true';
        dashboard.addEventListener('click', (event) => {
            const bar = event.target.closest('.monitoring-day-hit');
            if (!bar || !dashboard.contains(bar)) {
                return;
            }

            const detail = bar.closest('.monitoring-chart').querySelector('.monitoring-day-detail');
            detail.textContent = `${bar.dataset.day}: ${bar.dataset.total} pertanyaan · ${bar.dataset.answered} terjawab · ${bar.dataset.unanswered} belum terjawab`;
            detail.hidden = false;
        });
    });
</script>
