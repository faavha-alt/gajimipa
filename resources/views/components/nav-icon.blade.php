@props(['name'])

@php
    $paths = [
        'home' => 'M4 10.5L12 4l8 6.5M6 9.5V19a1 1 0 001 1h3v-5a1 1 0 011-1h2a1 1 0 011 1v5h3a1 1 0 001-1V9.5',
        'users' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-3.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-2.6-7.03',
        'building' => 'M3 21h18M6 21V6a1 1 0 011-1h4v16M18 21V10a1 1 0 00-1-1h-3v12M9 8h.01M9 11h.01M9 14h.01',
        'tag' => 'M7 7h.01M7 3h5.586a1 1 0 01.707.293l7.414 7.414a1 1 0 010 1.414l-8.586 8.586a1 1 0 01-1.414 0l-7.414-7.414A1 1 0 013 12.586V7a4 4 0 014-4z',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'upload' => 'M12 16V4m0 0L7 9m5-5l5 5M5 20h14',
        'minus-circle' => 'M9 12h6m-11 0a8 8 0 1016 0 8 8 0 00-16 0z',
        'calculator' => 'M6 3h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2zM8 7h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01',
        'check-badge' => 'M9 12.5l2 2 4-4.5m5 2a8 8 0 11-16 0 8 8 0 0116 0z',
        'document' => 'M8 3h6l4 4v13a1 1 0 01-1 1H8a1 1 0 01-1-1V4a1 1 0 011-1zM14 3v4h4M9 12h6M9 16h6',
        'receipt' => 'M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2V3zM8 8h8M8 12h8M8 16h4',
        'archive' => 'M3 7h18M4 7v12a1 1 0 001 1h14a1 1 0 001-1V7M9 11h6M2 4h20v3H2V4z',
        'chart' => 'M4 20V10m6 10V4m6 16v-7m6 7V8',
        'mail' => 'M4 6h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1zM3 7l9 6 9-6',
        'shield' => 'M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z',
        'clock' => 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
        'cog' => 'M4 6h16M4 6a2 2 0 104 0 2 2 0 00-4 0zM4 12h16M14 12a2 2 0 104 0 2 2 0 00-4 0zM4 18h16M8 18a2 2 0 104 0 2 2 0 00-4 0z',
    ];

    $d = $paths[$name] ?? $paths['tag'];
@endphp

<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}" />
</svg>
