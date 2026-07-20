<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }}</title>
    <style>
        @page {
            margin: 28px;
        }

        body {
            color: #172033;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            line-height: 1.45;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #1f5eff;
            margin-bottom: 18px;
            padding-bottom: 14px;
        }

        .brand-row {
            width: 100%;
        }

        .brand-logo {
            width: 64px;
        }

        .brand-mark {
            background: #eef5ff;
            border: 2px solid #1f5eff;
            color: #1f5eff;
            font-size: 22px;
            font-weight: 800;
            height: 56px;
            line-height: 56px;
            text-align: center;
            width: 56px;
        }

        .brand-copy {
            padding-left: 12px;
            vertical-align: middle;
        }

        .eyebrow {
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            color: #0f172a;
            font-size: 24px;
            margin-top: 4px;
        }

        .meta {
            color: #64748b;
            margin-top: 5px;
        }

        .kpis {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin: 0 -8px 16px;
        }

        .kpi {
            background: #f8fafc;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            padding: 10px;
            width: 20%;
        }

        .kpi-label {
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
        }

        .kpi-value {
            color: #0f172a;
            font-size: 24px;
            font-weight: 800;
            margin-top: 4px;
        }

        .kpi-detail {
            color: #64748b;
            font-size: 9px;
            margin-top: 2px;
        }

        .grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
            margin: 0 -10px 14px;
        }

        .panel {
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            padding: 12px;
            vertical-align: top;
            width: 33.33%;
        }

        h2 {
            color: #0f172a;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .row {
            margin-bottom: 8px;
        }

        .row-top {
            width: 100%;
        }

        .row-label {
            color: #334155;
            font-weight: 700;
        }

        .row-value {
            color: #0f172a;
            font-weight: 800;
            text-align: right;
        }

        .bar {
            background: #e2e8f0;
            border-radius: 999px;
            height: 6px;
            margin-top: 3px;
            overflow: hidden;
        }

        .bar-fill {
            background: #2563eb;
            height: 6px;
        }

        table.data {
            border-collapse: collapse;
            margin-top: 8px;
            width: 100%;
        }

        table.data th {
            background: #eef2ff;
            color: #475569;
            font-size: 9px;
            letter-spacing: .04em;
            padding: 7px 8px;
            text-align: left;
            text-transform: uppercase;
        }

        table.data td {
            border-bottom: 1px solid #e2e8f0;
            padding: 7px 8px;
            vertical-align: top;
        }

        .section {
            margin-top: 14px;
        }

        .muted {
            color: #64748b;
        }

        .badge {
            background: #eef2ff;
            border-radius: 999px;
            color: #1d4ed8;
            display: inline-block;
            font-size: 9px;
            font-weight: 800;
            padding: 3px 7px;
        }
    </style>
</head>
<body>
    <section class="header">
        <table class="brand-row">
            <tr>
                <td class="brand-logo">
                    <div class="brand-mark">C</div>
                </td>
                <td class="brand-copy">
                    <p class="eyebrow">CAPYHELP</p>
                    <h1>{{ $title }}</h1>
                    <p class="meta">{{ $generatedLabel }}: {{ $generatedAt->format('Y-m-d H:i') }}</p>
                </td>
            </tr>
        </table>
    </section>

    <table class="kpis">
        <tr>
            @foreach ($kpis as $kpi)
                <td class="kpi">
                    <p class="kpi-label">{{ $kpi['label'] }}</p>
                    <p class="kpi-value">{{ $kpi['value'] }}</p>
                    <p class="kpi-detail">{{ $kpi['detail'] }}</p>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td class="panel">
                <h2>{{ __('helpdesk.reports.sections.statuses') }}</h2>
                @foreach ($statuses as $item)
                    <div class="row">
                        <table class="row-top"><tr>
                            <td class="row-label">{{ $item['label'] }}</td>
                            <td class="row-value">{{ $item['value'] }} ({{ $item['percentage'] }}%)</td>
                        </tr></table>
                        <div class="bar"><div class="bar-fill" style="width: {{ $item['percentage'] }}%;"></div></div>
                    </div>
                @endforeach
            </td>
            <td class="panel">
                <h2>{{ __('helpdesk.reports.sections.priorities') }}</h2>
                @foreach ($priorities as $item)
                    <div class="row">
                        <table class="row-top"><tr>
                            <td class="row-label">{{ $item['label'] }}</td>
                            <td class="row-value">{{ $item['value'] }} ({{ $item['percentage'] }}%)</td>
                        </tr></table>
                        <div class="bar"><div class="bar-fill" style="width: {{ $item['percentage'] }}%;"></div></div>
                    </div>
                @endforeach
            </td>
            <td class="panel">
                <h2>{{ __('helpdesk.reports.sections.channels') }}</h2>
                @foreach ($channels as $item)
                    <div class="row">
                        <table class="row-top"><tr>
                            <td class="row-label">{{ $item['label'] }}</td>
                            <td class="row-value">{{ $item['value'] }} ({{ $item['percentage'] }}%)</td>
                        </tr></table>
                        <div class="bar"><div class="bar-fill" style="width: {{ $item['percentage'] }}%;"></div></div>
                    </div>
                @endforeach
            </td>
        </tr>
    </table>

    <section class="section">
        <h2>{{ __('helpdesk.reports.sections.team_workload') }}</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('helpdesk.reports.table.agent') }}</th>
                    <th>{{ __('helpdesk.reports.table.all') }}</th>
                    <th>{{ __('helpdesk.reports.table.active') }}</th>
                    <th>{{ __('helpdesk.reports.table.closed_resolved') }}</th>
                    <th>{{ __('helpdesk.reports.table.urgent') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agents as $agent)
                    <tr>
                        <td><strong>{{ $agent['name'] }}</strong></td>
                        <td>{{ $agent['total'] }}</td>
                        <td>{{ $agent['active'] }}</td>
                        <td>{{ $agent['completed'] }}</td>
                        <td>{{ $agent['urgent'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('helpdesk.common.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="section">
        <h2>{{ __('helpdesk.reports.sections.recent_tickets') }}</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('helpdesk.reports.table.number') }}</th>
                    <th>{{ __('helpdesk.reports.table.requester') }}</th>
                    <th>{{ __('helpdesk.reports.table.subject') }}</th>
                    <th>{{ __('helpdesk.reports.table.status') }}</th>
                    <th>{{ __('helpdesk.reports.table.priority') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td><strong>{{ $ticket['number'] }}</strong></td>
                        <td>
                            {{ $ticket['requester_name'] }}<br>
                            <span class="muted">{{ $ticket['requester_email'] }}</span>
                        </td>
                        <td>{{ $ticket['subject'] }}</td>
                        <td><span class="badge">{{ $ticket['status'] }}</span></td>
                        <td>{{ $ticket['priority'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('helpdesk.common.no_tickets') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</body>
</html>
