<div class="letterhead">
    <h1>{{ $building->name ?? 'Green Valley' }}</h1>
    <div class="muted">{{ $reportTitle }}</div>
    <div class="muted">Generated: {{ now()->format('d M Y, h:i A') }}</div>
</div>
