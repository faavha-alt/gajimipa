<?php

use App\Models\SystemSetting;
use App\Support\AuditLogger;
use App\Support\Settings;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $nama_universitas = '';

    public string $nama_fakultas = '';

    public string $alamat_fakultas = '';

    public string $prefix_nomor_slip = '';

    public string $prefix_nomor_potongan = '';

    public function mount(): void
    {
        Gate::authorize('settings.manage');

        $values = Settings::all();
        $this->nama_universitas = $values['nama_universitas'];
        $this->nama_fakultas = $values['nama_fakultas'];
        $this->alamat_fakultas = $values['alamat_fakultas'];
        $this->prefix_nomor_slip = $values['prefix_nomor_slip'];
        $this->prefix_nomor_potongan = $values['prefix_nomor_potongan'];
    }

    public function save(): void
    {
        Gate::authorize('settings.manage');

        $validated = $this->validate([
            'nama_universitas' => ['required', 'string', 'max:150'],
            'nama_fakultas' => ['required', 'string', 'max:150'],
            'alamat_fakultas' => ['nullable', 'string', 'max:255'],
            'prefix_nomor_slip' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\/\-]+$/'],
            'prefix_nomor_potongan' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\/\-]+$/'],
        ], [
            'prefix_nomor_slip.regex' => 'Format prefix hanya boleh huruf kapital, angka, garis miring, dan strip.',
            'prefix_nomor_potongan.regex' => 'Format prefix hanya boleh huruf kapital, angka, garis miring, dan strip.',
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        AuditLogger::log('Ubah Pengaturan', 'Mengubah pengaturan kop surat & format nomor dokumen.', $validated);

        session()->flash('status', 'Pengaturan berhasil disimpan.');
    }
}; ?>

<div class="w-full max-w-2xl space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Pengaturan</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kop surat & format nomor dokumen yang dipakai di seluruh Slip Gaji, Bukti Potongan, Rekap Setoran, dan Laporan — hanya Super Admin.</p>
    </div>

    <x-flash :status="session('status')" />

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Kop Surat Dokumen</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ditampilkan di kepala setiap PDF (slip gaji, bukti potongan, rekap setoran, laporan).</p>

            <div class="mt-4 space-y-4">
                <div>
                    <x-input-label for="nama_fakultas" value="Nama Fakultas" />
                    <x-text-input wire:model="nama_fakultas" id="nama_fakultas" type="text" class="mt-1 block w-full" />
                    <x-input-error class="mt-2" :messages="$errors->get('nama_fakultas')" />
                </div>

                <div>
                    <x-input-label for="nama_universitas" value="Nama Universitas" />
                    <x-text-input wire:model="nama_universitas" id="nama_universitas" type="text" class="mt-1 block w-full" />
                    <x-input-error class="mt-2" :messages="$errors->get('nama_universitas')" />
                </div>

                <div>
                    <x-input-label for="alamat_fakultas" value="Alamat (opsional)" />
                    <x-text-input wire:model="alamat_fakultas" id="alamat_fakultas" type="text" class="mt-1 block w-full" placeholder="Kosongkan jika tidak ingin ditampilkan" />
                    <x-input-error class="mt-2" :messages="$errors->get('alamat_fakultas')" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Format Nomor Dokumen</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Prefix nomor berurutan (§19). Contoh hasil: <span class="font-mono">{{ $prefix_nomor_slip ?: 'SLIP/MIPA' }}/VIII/2026/0001</span>. Mengubah ini tidak mengubah nomor dokumen yang sudah pernah diterbitkan.</p>

            <div class="mt-4 space-y-4">
                <div>
                    <x-input-label for="prefix_nomor_slip" value="Prefix Nomor Slip Gaji" />
                    <x-text-input wire:model="prefix_nomor_slip" id="prefix_nomor_slip" type="text" class="mt-1 block w-full font-mono" />
                    <x-input-error class="mt-2" :messages="$errors->get('prefix_nomor_slip')" />
                </div>

                <div>
                    <x-input-label for="prefix_nomor_potongan" value="Prefix Nomor Bukti Potongan" />
                    <x-text-input wire:model="prefix_nomor_potongan" id="prefix_nomor_potongan" type="text" class="mt-1 block w-full font-mono" />
                    <x-input-error class="mt-2" :messages="$errors->get('prefix_nomor_potongan')" />
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <x-primary-button type="submit">Simpan Pengaturan</x-primary-button>
        </div>
    </form>
</div>
