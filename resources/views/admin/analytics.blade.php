<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>GoalBot Analytics</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; background: #0b0f1a; color: #e4e7ef; padding: 2rem; }
    h1 { margin: 0 0 0.25rem; }
    .muted { color: #8892a8; font-size: 0.875rem; }
    .grid { display: grid; gap: 1rem; }
    .kpis { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin: 1.5rem 0; }
    .card { background: #131826; border: 1px solid #1f2638; border-radius: 12px; padding: 1.25rem; }
    .kpi .label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #8892a8; }
    .kpi .value { font-size: 2rem; font-weight: 700; margin-top: 0.25rem; }
    .kpi .sub { font-size: 0.75rem; color: #8892a8; margin-top: 0.25rem; }
    table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    th, td { text-align: left; padding: 0.6rem 0.5rem; border-bottom: 1px solid #1f2638; }
    th { color: #8892a8; font-weight: 500; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; }
    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 720px) { .row2 { grid-template-columns: 1fr; } }
    .bar { height: 6px; background: #1f2638; border-radius: 3px; overflow: hidden; margin-top: 0.25rem; }
    .bar > div { height: 100%; background: linear-gradient(90deg, #4ade80, #22d3ee); }
</style>
</head>
<body>
    <h1>📊 GoalBot Analytics</h1>
    <p class="muted">Since {{ $since->toDateString() }}</p>

    <div class="grid kpis">
        <div class="card kpi"><div class="label">Page Views</div><div class="value">{{ number_format($visits) }}</div></div>
        <div class="card kpi"><div class="label">CTA Clicks</div><div class="value">{{ number_format($clicks) }}</div><div class="sub">{{ $visits ? round($clicks / $visits * 100, 1) : 0 }}% CTR</div></div>
        <div class="card kpi"><div class="label">Subscribers</div><div class="value">{{ number_format($subscribers) }}</div><div class="sub">{{ $clicks ? round($subscribers / $clicks * 100, 1) : 0 }}% of clicks</div></div>
        <div class="card kpi"><div class="label">Active</div><div class="value">{{ number_format($active) }}</div></div>
        <div class="card kpi"><div class="label">Paid Tx</div><div class="value">{{ number_format($paidCount) }}</div></div>
        <div class="card kpi"><div class="label">Revenue</div><div class="value">KES {{ number_format($revenue, 0) }}</div></div>
    </div>

    <div class="row2">
        <div class="card">
            <h3>By Source</h3>
            <table>
                <thead><tr><th>Source</th><th>Visits</th><th>Clicks</th><th>Subs</th></tr></thead>
                <tbody>
                @forelse($bySource as $s)
                    <tr>
                        <td>{{ $s->source }}</td>
                        <td>{{ $s->visits }}</td>
                        <td>{{ $s->clicks }}</td>
                        <td>{{ $subsBySource[$s->source]->subs ?? 0 }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">No data yet</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>By Country</h3>
            <table>
                <thead><tr><th>Country</th><th>Visits</th></tr></thead>
                <tbody>
                @forelse($byCountry as $c)
                    <tr>
                        <td>{{ $c->country }}</td>
                        <td>{{ $c->visits }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">No data yet</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top: 1rem;">
        <h3>Last 14 Days</h3>
        <table>
            <thead><tr><th>Date</th><th>Views</th><th>Clicks</th><th>CTR</th></tr></thead>
            <tbody>
            @php $maxView = max($daily->max('views') ?: 1, 1); @endphp
            @forelse($daily as $d)
                <tr>
                    <td>{{ $d->date }}</td>
                    <td>{{ $d->views }}<div class="bar"><div style="width: {{ round($d->views / $maxView * 100) }}%"></div></div></td>
                    <td>{{ $d->clicks }}</td>
                    <td>{{ $d->views ? round($d->clicks / $d->views * 100, 1) . '%' : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No data yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
