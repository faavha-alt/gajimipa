@php $settings = \App\Support\Settings::all(); @endphp
<div class="fakultas">{{ $settings['nama_fakultas'] }}</div>
<div class="univ">{{ $settings['nama_universitas'] }}</div>
@if ($settings['alamat_fakultas'])
    <div class="alamat">{{ $settings['alamat_fakultas'] }}</div>
@endif
