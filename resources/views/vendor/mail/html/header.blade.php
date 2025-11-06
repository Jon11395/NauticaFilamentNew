@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@php
    $logoPath = public_path('images/logo1.png');
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoMime = 'image/png';
        $logoUrl = 'data:' . $logoMime . ';base64,' . $logoData;
    } else {
        // Fallback to URL if file doesn't exist
        $appUrl = rtrim(config('app.url', 'http://localhost'), '/');
        $logoUrl = $appUrl . '/images/logo1.png';
    }
@endphp
<img src="{{ $logoUrl }}" 
     alt="{{ config('app.name', 'Náutica') }} Logo" 
     style="max-height: 50px; display: block; border: none; outline: none; text-decoration: none;">
</a>
</td>
</tr>
