<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>GoalBot — Subscribers</title>
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; background: #0b0f1a; color: #e4e7ef; padding: 0; }
a { color: #4ade80; text-decoration: none; }
a:hover { text-decoration: underline; }

/* Layout */
.topbar { background: #0f1623; border-bottom: 1px solid #1f2638; padding: 0.875rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.topbar h1 { margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem; }
.topbar nav a { font-size: 0.85rem; color: #8892a8; margin-left: 1.25rem; }
.topbar nav a.active, .topbar nav a:hover { color: #e4e7ef; }
.wrap { padding: 1.5rem; max-width: 1400px; margin: 0 auto; }

/* KPI row */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
.kpi { background: #131826; border: 1px solid #1f2638; border-radius: 10px; padding: 1rem 1.1rem; }
.kpi .label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #8892a8; }
.kpi .val   { font-size: 1.75rem; font-weight: 700; margin-top: 0.15rem; line-height: 1; }
.kpi .sub   { font-size: 0.7rem; color: #8892a8; margin-top: 0.2rem; }
.kpi.green .val { color: #4ade80; }
.kpi.blue  .val { color: #60a5fa; }
.kpi.amber .val { color: #fbbf24; }
.kpi.red   .val { color: #f87171; }

/* Charts row */
.charts { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem; }
@media (max-width: 700px) { .charts { grid-template-columns: 1fr; } }
.card { background: #131826; border: 1px solid #1f2638; border-radius: 10px; padding: 1.1rem; }
.card h3 { margin: 0 0 0.75rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #8892a8; }
.bar-row { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.45rem; font-size: 0.8rem; }
.bar-row .name { width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bar-row .bar { flex: 1; height: 8px; background: #1f2638; border-radius: 4px; overflow: hidden; }
.bar-row .bar > div { height: 100%; background: linear-gradient(90deg, #4ade80, #22d3ee); border-radius: 4px; }
.bar-row .count { width: 32px; text-align: right; color: #8892a8; }

/* Flash */
.flash { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; }
.flash.success { background: #052e16; border: 1px solid #166534; color: #4ade80; }
.flash.error   { background: #2d0909; border: 1px solid #7f1d1d; color: #f87171; }

/* Toolbar */
.toolbar { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; margin-bottom: 1rem; }
.toolbar input[type=text] { background: #131826; border: 1px solid #2a3248; border-radius: 7px; color: #e4e7ef; padding: 0.45rem 0.75rem; font-size: 0.85rem; width: 220px; outline: none; }
.toolbar input[type=text]:focus { border-color: #4ade80; }
.toolbar select { background: #131826; border: 1px solid #2a3248; border-radius: 7px; color: #e4e7ef; padding: 0.45rem 0.65rem; font-size: 0.85rem; outline: none; }
.btn { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.9rem; border-radius: 7px; font-size: 0.8rem; font-weight: 500; cursor: pointer; border: none; transition: opacity .15s; }
.btn:hover { opacity: 0.85; }
.btn-green  { background: #166534; color: #4ade80; }
.btn-blue   { background: #1e3a5f; color: #60a5fa; }
.btn-amber  { background: #451a03; color: #fbbf24; }
.btn-red    { background: #450a0a; color: #f87171; }
.btn-ghost  { background: #1f2638; color: #e4e7ef; }
.btn-sm { padding: 0.3rem 0.6rem; font-size: 0.75rem; }

/* Filter pills */
.pill { display: inline-block; padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.75rem; border: 1px solid #2a3248; color: #8892a8; }
.pill.active { background: #1f3a25; border-color: #166534; color: #4ade80; }

/* Table */
.table-wrap { background: #131826; border: 1px solid #1f2638; border-radius: 10px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
thead th { position: sticky; top: 0; background: #0f1623; padding: 0.65rem 0.75rem; text-align: left; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #8892a8; font-weight: 500; border-bottom: 1px solid #1f2638; white-space: nowrap; }
tbody td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #1a2130; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #161d2e; }

/* Badges */
.badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
.badge-green  { background: #052e16; color: #4ade80; border: 1px solid #166534; }
.badge-blue   { background: #0c1a2e; color: #60a5fa; border: 1px solid #1e3a5f; }
.badge-amber  { background: #1c0a00; color: #fbbf24; border: 1px solid #451a03; }
.badge-red    { background: #1c0505; color: #f87171; border: 1px solid #450a0a; }
.badge-gray   { background: #1a2130; color: #8892a8; border: 1px solid #2a3248; }

/* Edit drawer */
.drawer-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.65); z-index: 100; }
.drawer-overlay.open { display: block; }
.drawer { position: fixed; right: 0; top: 0; bottom: 0; width: 380px; background: #0f1623; border-left: 1px solid #1f2638; z-index: 101; padding: 1.5rem; overflow-y: auto; transform: translateX(100%); transition: transform .2s; }
.drawer.open { transform: translateX(0); }
.drawer h2 { margin: 0 0 1.25rem; font-size: 1rem; }
.field { margin-bottom: 1rem; }
.field label { display: block; font-size: 0.75rem; color: #8892a8; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.04em; }
.field input, .field select { width: 100%; background: #131826; border: 1px solid #2a3248; border-radius: 7px; color: #e4e7ef; padding: 0.5rem 0.75rem; font-size: 0.875rem; outline: none; }
.field input:focus, .field select:focus { border-color: #4ade80; }
.field-row { display: flex; gap: 0.75rem; }
.field-row .field { flex: 1; }
.toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 0.6rem 0; border-bottom: 1px solid #1f2638; font-size: 0.85rem; }
.toggle { position: relative; display: inline-block; width: 36px; height: 20px; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle .slider { position: absolute; inset: 0; background: #2a3248; border-radius: 20px; transition: .2s; cursor: pointer; }
.toggle .slider:before { position: absolute; content: ''; height: 14px; width: 14px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .2s; }
.toggle input:checked + .slider { background: #166534; }
.toggle input:checked + .slider:before { transform: translateX(16px); }
.drawer-actions { display: flex; gap: 0.6rem; margin-top: 1.25rem; }

/* Broadcast panel */
.broadcast { background: #131826; border: 1px solid #2a3248; border-radius: 10px; padding: 1.1rem; margin-bottom: 1.5rem; }
.broadcast h3 { margin: 0 0 0.75rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #8892a8; }
.broadcast textarea { width: 100%; background: #0b0f1a; border: 1px solid #2a3248; border-radius: 7px; color: #e4e7ef; padding: 0.65rem 0.75rem; font-size: 0.875rem; resize: vertical; min-height: 80px; outline: none; font-family: inherit; }
.broadcast textarea:focus { border-color: #4ade80; }
.broadcast-row { display: flex; gap: 0.6rem; align-items: flex-end; flex-wrap: wrap; margin-top: 0.75rem; }
.broadcast-row select { background: #0b0f1a; border: 1px solid #2a3248; border-radius: 7px; color: #e4e7ef; padding: 0.5rem 0.65rem; font-size: 0.85rem; outline: none; }

/* Pagination */
.pager { display: flex; gap: 0.4rem; justify-content: center; margin-top: 1.25rem; flex-wrap: wrap; }
.pager a, .pager span { padding: 0.35rem 0.7rem; border-radius: 6px; font-size: 0.8rem; background: #131826; border: 1px solid #1f2638; color: #8892a8; }
.pager span.active, .pager a:hover { background: #1f3a25; border-color: #166534; color: #4ade80; }

@media (max-width: 900px) { .drawer { width: 100%; } }
</style>
</head>
<body>

<div class="topbar">
    <h1>⚽ GoalBot Admin</h1>
    <nav>
        <a href="{{ route('admin.analytics', request('key') ? ['key' => request('key')] : []) }}">Analytics</a>
        <a href="{{ route('admin.subscribers', request('key') ? ['key' => request('key')] : []) }}" class="active">Subscribers</a>
        @auth
        <form method="POST" action="{{ route('admin.logout') }}" style="display:inline;margin-left:1.25rem;">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm">Log out</button>
        </form>
        @endauth
    </nav>
</div>

<div class="wrap">

    @if(session('success'))
        <div class="flash success">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="flash error">❌ {{ session('error') }}</div>
    @endif

    {{-- KPIs --}}
    <div class="kpi-grid">
        <div class="kpi green">
            <div class="label">Total</div>
            <div class="val">{{ $stats['total'] }}</div>
            <div class="sub">all subscribers</div>
        </div>
        <div class="kpi blue">
            <div class="label">Paid</div>
            <div class="val">{{ $stats['paid'] }}</div>
            <div class="sub">full_tournament + per_match</div>
        </div>
        <div class="kpi amber">
            <div class="label">Free</div>
            <div class="val">{{ $stats['free'] }}</div>
            <div class="sub">no subscription</div>
        </div>
        <div class="kpi green">
            <div class="label">Active</div>
            <div class="val">{{ $stats['active'] }}</div>
            <div class="sub">notifs enabled</div>
        </div>
        <div class="kpi blue">
            <div class="label">Notify All</div>
            <div class="val">{{ $stats['notify_all'] }}</div>
            <div class="sub">all matches</div>
        </div>
        <div class="kpi blue">
            <div class="label">MBM</div>
            <div class="val">{{ $stats['mbm'] }}</div>
            <div class="sub">min-by-min mode</div>
        </div>
        <div class="kpi green">
            <div class="label">Today</div>
            <div class="val">+{{ $stats['new_today'] }}</div>
            <div class="sub">new today</div>
        </div>
        <div class="kpi green">
            <div class="label">This Week</div>
            <div class="val">+{{ $stats['new_week'] }}</div>
            <div class="sub">last 7 days</div>
        </div>
    </div>

    {{-- Breakdowns --}}
    <div class="charts">
        <div class="card">
            <h3>Commentary Mode</h3>
            @php $modeTotal = $modes->sum(); @endphp
            @foreach(['digest' => '🎯 Smart', 'live' => '⚡ Live', 'minute_by_minute' => '🕐 MBM'] as $key => $label)
                @php $n = $modes->get($key, 0); @endphp
                <div class="bar-row">
                    <span class="name">{{ $label }}</span>
                    <div class="bar"><div style="width:{{ $modeTotal ? round($n/$modeTotal*100) : 0 }}%"></div></div>
                    <span class="count">{{ $n }}</span>
                </div>
            @endforeach
        </div>
        <div class="card">
            <h3>Subscription Type</h3>
            @php $typeTotal = $types->sum(); @endphp
            @foreach(['full_tournament' => '🏆 Full Tournament', 'per_match' => '⚽ Per Match', 'free' => '🆓 Free'] as $key => $label)
                @php $n = $types->get($key, 0); @endphp
                <div class="bar-row">
                    <span class="name">{{ $label }}</span>
                    <div class="bar"><div style="width:{{ $typeTotal ? round($n/$typeTotal*100) : 0 }}%"></div></div>
                    <span class="count">{{ $n }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Broadcast --}}
    <div class="broadcast">
        <h3>📢 Broadcast Message</h3>
        <form method="POST" action="/admin/broadcast?key={{ request('key') }}" onsubmit="return confirm('Send broadcast to selected audience?')">
            @csrf
            <textarea name="message" placeholder="Type your message to send via WhatsApp…" required></textarea>
            <div class="broadcast-row">
                <select name="audience">
                    <option value="all">All active subscribers ({{ $stats['active'] }})</option>
                    <option value="paid">Paid only ({{ $stats['paid'] }})</option>
                    <option value="free">Free only ({{ $stats['free'] }})</option>
                    <option value="active">Active + notifs on ({{ $stats['active'] }})</option>
                </select>
                <button type="submit" class="btn btn-amber">📤 Send Broadcast</button>
            </div>
        </form>
    </div>

    {{-- Toolbar --}}
    <div class="toolbar">
        <form method="GET" action="" style="display:contents">
            <input type="hidden" name="key" value="{{ request('key') }}">
            <input type="text" name="search" placeholder="Search phone / team…" value="{{ request('search') }}">
            <select name="filter" onchange="this.form.submit()">
                <option value="">All subscribers</option>
                <option value="paid"     @selected(request('filter') === 'paid')>Paid</option>
                <option value="free"     @selected(request('filter') === 'free')>Free</option>
                <option value="active"   @selected(request('filter') === 'active')>Active</option>
                <option value="inactive" @selected(request('filter') === 'inactive')>Inactive</option>
                <option value="mbm"      @selected(request('filter') === 'mbm')>MBM mode</option>
            </select>
            <button type="submit" class="btn btn-ghost">🔍 Search</button>
            @if(request('search') || request('filter'))
                <a href="/admin/subscribers?key={{ request('key') }}" class="btn btn-ghost">✕ Clear</a>
            @endif
        </form>
        <span style="margin-left:auto; font-size:0.8rem; color:#8892a8;">
            {{ $subscribers->total() }} result{{ $subscribers->total() !== 1 ? 's' : '' }}
        </span>
    </div>

    {{-- Table --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Phone</th>
                    <th>Team</th>
                    <th>Subscription</th>
                    <th>Mode</th>
                    <th>Notifs</th>
                    <th>All Matches</th>
                    <th>Active</th>
                    <th>Window</th>
                    <th>Joined</th>
                    <th>Last Alert</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscribers as $sub)
                <tr>
                    <td style="color:#8892a8">{{ $sub->id }}</td>
                    <td style="font-family:monospace">{{ $sub->phone_number }}</td>
                    <td>{{ $sub->favorite_team ?? '—' }}</td>
                    <td>
                        @php
                            $typeClass = match($sub->subscription_type) {
                                'full_tournament' => 'badge-blue',
                                'per_match'       => 'badge-green',
                                default           => 'badge-gray',
                            };
                        @endphp
                        <span class="badge {{ $typeClass }}">{{ $sub->subscription_type ?? 'free' }}</span>
                    </td>
                    <td>
                        @php
                            $modeClass = match($sub->commentary_mode ?? 'digest') {
                                'live'             => 'badge-green',
                                'minute_by_minute' => 'badge-amber',
                                default            => 'badge-gray',
                            };
                            $modeLabel = match($sub->commentary_mode ?? 'digest') {
                                'live'             => '⚡ live',
                                'minute_by_minute' => '🕐 mbm',
                                default            => '🎯 smart',
                            };
                        @endphp
                        <span class="badge {{ $modeClass }}">{{ $modeLabel }}</span>
                    </td>
                    <td>
                        @if($sub->notifications_enabled)
                            <span class="badge badge-green">on</span>
                        @else
                            <span class="badge badge-red">off</span>
                        @endif
                    </td>
                    <td>
                        @if($sub->notify_all_matches)
                            <span class="badge badge-blue">all</span>
                        @else
                            <span class="badge badge-gray">fav</span>
                        @endif
                    </td>
                    <td>
                        @if($sub->is_active)
                            <span class="badge badge-green">✓</span>
                        @else
                            <span class="badge badge-red">✗</span>
                        @endif
                    </td>
                    <td>
                        @if($sub->window_failed)
                            <span class="badge badge-red">closed</span>
                        @elseif($sub->last_message_in_at && $sub->last_message_in_at->gt(now()->subHours(24)))
                            <span class="badge badge-green">open</span>
                        @else
                            <span class="badge badge-gray">unknown</span>
                        @endif
                    </td>
                    <td style="color:#8892a8; white-space:nowrap">{{ $sub->created_at->format('d M y') }}</td>
                    <td style="color:#8892a8; white-space:nowrap">{{ $sub->last_notification_at ? $sub->last_notification_at->diffForHumans() : '—' }}</td>
                    <td>
                        <button class="btn btn-ghost btn-sm" onclick="openDrawer({{ $sub->id }}, {{ $sub->toJson() }})">✏️ Edit</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="12" style="text-align:center; padding:2rem; color:#8892a8">No subscribers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($subscribers->hasPages())
    <div class="pager">
        @foreach($subscribers->links()->elements[0] as $page => $url)
            @if($page == $subscribers->currentPage())
                <span class="active">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach
    </div>
    @endif

</div>

{{-- Edit Drawer --}}
<div class="drawer-overlay" id="overlay" onclick="closeDrawer()"></div>
<div class="drawer" id="drawer">
    <h2>✏️ Edit Subscriber</h2>
    <form method="POST" id="editForm">
        @csrf
        <div class="field">
            <label>Phone Number</label>
            <input type="text" id="d_phone" disabled style="opacity:.5">
        </div>
        <div class="field">
            <label>Favourite Team</label>
            <input type="text" name="favorite_team" id="d_team" placeholder="e.g. Brazil">
        </div>
        <div class="field">
            <label>Subscription Type</label>
            <select name="subscription_type" id="d_subtype">
                <option value="free">Free</option>
                <option value="full_tournament">Full Tournament</option>
                <option value="per_match">Per Match</option>
            </select>
        </div>
        <div class="field">
            <label>Commentary Mode</label>
            <select name="commentary_mode" id="d_mode">
                <option value="digest">🎯 Smart Alerts</option>
                <option value="live">⚡ Live Updates</option>
                <option value="minute_by_minute">🕐 Minute by Minute</option>
            </select>
        </div>
        <div class="toggle-row">
            <span>Notifications Enabled</span>
            <label class="toggle">
                <input type="hidden" name="notifications_enabled" value="0">
                <input type="checkbox" name="notifications_enabled" id="d_notifs" value="1">
                <span class="slider"></span>
            </label>
        </div>
        <div class="toggle-row">
            <span>Notify All Matches</span>
            <label class="toggle">
                <input type="hidden" name="notify_all_matches" value="0">
                <input type="checkbox" name="notify_all_matches" id="d_all" value="1">
                <span class="slider"></span>
            </label>
        </div>
        <div class="toggle-row">
            <span>Is Active</span>
            <label class="toggle">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="d_active" value="1">
                <span class="slider"></span>
            </label>
        </div>
        <div class="drawer-actions">
            <button type="submit" class="btn btn-green">💾 Save Changes</button>
            <button type="button" class="btn btn-ghost" onclick="closeDrawer()">Cancel</button>
        </div>
    </form>
</div>

<script>
const KEY = '{{ request('key') }}';

function openDrawer(id, sub) {
    document.getElementById('d_phone').value    = sub.phone_number;
    document.getElementById('d_team').value     = sub.favorite_team || '';
    document.getElementById('d_subtype').value  = sub.subscription_type || 'free';
    document.getElementById('d_mode').value     = sub.commentary_mode || 'digest';
    document.getElementById('d_notifs').checked = !!sub.notifications_enabled;
    document.getElementById('d_all').checked    = !!sub.notify_all_matches;
    document.getElementById('d_active').checked = !!sub.is_active;
    document.getElementById('editForm').action  = `/admin/subscribers/${id}?key=${KEY}`;
    document.getElementById('drawer').classList.add('open');
    document.getElementById('overlay').classList.add('open');
}

function closeDrawer() {
    document.getElementById('drawer').classList.remove('open');
    document.getElementById('overlay').classList.remove('open');
}

// Fix duplicate checkbox: only send checked value when ticked
document.querySelectorAll('.toggle input[type=checkbox]').forEach(cb => {
    cb.addEventListener('change', function() {
        const hidden = this.previousElementSibling;
        if (hidden && hidden.type === 'hidden') {
            hidden.disabled = this.checked;
        }
    });
    // Init
    if (cb.checked) {
        const hidden = cb.previousElementSibling;
        if (hidden && hidden.type === 'hidden') hidden.disabled = true;
    }
});
</script>

</body>
</html>
