<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Handover report - {{ $property->name }}</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f4f4f5;
            color: #18181b;
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        main {
            max-width: 1000px;
            margin: 24px auto;
            padding: 32px;
            background: white;
        }

        h1 { margin-bottom: 4px; }
        h2 { margin-top: 32px; }
        h3 { margin: 0 0 10px; }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        button {
            padding: 10px 16px;
            border: 0;
            border-radius: 6px;
            background: #1d4ed8;
            color: white;
            cursor: pointer;
            font: inherit;
        }

        a { color: #1d4ed8; }
        .muted { color: #52525b; }
        .text { white-space: pre-line; overflow-wrap: anywhere; }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 24px 0;
        }

        .summary div, .notice, article {
            padding: 16px;
            border: 1px solid #d4d4d8;
            border-radius: 6px;
        }

        .summary strong {
            display: block;
            font-size: 26px;
        }

        article { margin: 16px 0; }

        dl {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin: 0;
        }

        dt { font-weight: bold; }
        dd { margin: 0; overflow-wrap: anywhere; }
        footer { margin-top: 32px; border-top: 1px solid #d4d4d8; padding-top: 16px; }

        @media (max-width: 600px) {
            main { margin: 0; padding: 16px; }
            .summary, dl { grid-template-columns: 1fr; }
        }

        @page {
            size: A4;
            margin: 15mm;
        }

        @media print {
    body {
        background: white;
        font-size: 10pt;
        line-height: 1.35;
    }

    main { max-width: none; margin: 0; padding: 0; }
    .toolbar { display: none; }
    h1 { font-size: 20pt; }
    h2 { margin-top: 18px; font-size: 14pt; }
    h3 { font-size: 11pt; }

    .summary {
        grid-template-columns: repeat(3, 1fr);
        margin: 14px 0;
    }

    .summary div, .notice, article { padding: 10px; }
    .summary strong { font-size: 20px; }
    dl { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    p { margin: 8px 0; }
    h2, h3 { break-after: avoid; }
    article { margin: 10px 0; break-inside: avoid; }
    footer { margin-top: 14px; padding-top: 10px; font-size: 9pt; }
}
    </style>
</head>
<body>
<main>
    <nav class="toolbar" aria-label="Report actions">
        <a href="{{ route('properties.show', $property) }}">
            Back to property
        </a>
        <button type="button" onclick="window.print()">
            Print / Save as PDF
        </button>
    </nav>

    <header>
        <h1>Property handover report</h1>
        <h2>{{ $property->name }}</h2>
        <p class="text">{{ $property->address }}</p>
        <p>
            Target handover:
            {{ $property->target_handover_date?->format('d M Y') ?? 'Not set' }}
        </p>
        <p class="muted">
            Generated: {{ $generatedAt->format('d M Y H:i') }}
            ({{ config('app.timezone') }})
        </p>
    </header>

    <section class="summary" aria-label="Defect totals">
        <div>Total defects <strong>{{ $total }}</strong></div>
        <div>Unresolved <strong>{{ $unresolved->count() }}</strong></div>
        <div>Verified <strong>{{ $verified->count() }}</strong></div>
    </section>

    <div class="notice">
        @if ($total === 0)
            No defects have been recorded for this property.
            This does not confirm that an inspection has been completed.
        @elseif ($unresolved->isNotEmpty())
            Outstanding defects remain.
            Repaired defects awaiting manager verification count as unresolved.
        @else
            All recorded defects are verified.
            This report does not replace formal handover approval.
        @endif
    </div>

    @foreach ([
        'Unresolved defects' => $unresolved,
        'Verified defects' => $verified,
    ] as $heading => $items)
        <section>
            <h2>{{ $heading }} ({{ $items->count() }})</h2>

            @forelse ($items as $defect)
                <article>
                    <h3>
                        DEF-{{ str_pad((string) $defect->id, 6, '0', STR_PAD_LEFT) }}
                        — {{ $defect->title }}
                    </h3>

                    <dl>
                        <div>
                            <dt>Room</dt>
                            <dd>{{ $defect->room?->name ?? 'Unavailable' }}</dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>{{ $defect->status->label() }}</dd>
                        </div>
                        <div>
                            <dt>Category</dt>
                            <dd>{{ ucfirst($defect->category) }}</dd>
                        </div>
                        <div>
                            <dt>Priority</dt>
                            <dd>{{ ucfirst($defect->priority) }}</dd>
                        </div>
                        <div>
                            <dt>Assigned contractor</dt>
                            <dd>{{ $defect->assignee?->name ?? 'Not assigned' }}</dd>
                        </div>
                        <div>
                            <dt>Due date</dt>
                            <dd>{{ $defect->due_date?->format('d M Y') ?? 'Not set' }}</dd>
                        </div>
                    </dl>

                    <p><strong>Description</strong></p>
                    <p class="text">{{ $defect->description }}</p>

                    @if ($defect->repair_notes)
                        <p><strong>Latest repair submission</strong></p>
                        <p class="text">{{ $defect->repair_notes }}</p>
                        <p>
                            Submitted:
                            {{ $defect->repaired_at?->format('d M Y H:i') ?? 'Not recorded' }}
                        </p>
                    @endif

                    @if ($defect->status === \App\Enums\DefectStatus::Verified)
                        <p>
                            <strong>Verified by:</strong>
                            {{ $defect->reviewer?->name ?? 'Unavailable' }}
                            <br>
                            <strong>Verified at:</strong>
                            {{ $defect->verified_at?->format('d M Y H:i') ?? 'Not recorded' }}
                        </p>
                    @endif

                    @if ($defect->reopen_reason)
                        <p><strong>Latest reopening reason</strong></p>
                        <p class="text">{{ $defect->reopen_reason }}</p>
                    @endif
                </article>
            @empty
                <p>None.</p>
            @endforelse
        </section>
    @endforeach

    <footer class="muted">
        Statuses reflect the records at report generation time.
        All times use {{ config('app.timezone') }}.
        Photos and complete activity history are available in the portal
        to authorized users.
    </footer>
</main>
</body>
</html>