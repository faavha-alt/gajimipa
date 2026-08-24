<?php

use App\Models\SystemSetting;
use App\Support\AuditLogger;
use App\Support\Settings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $nama_universitas = '';

    public string $nama_fakultas = '';

    public string $alamat_fakultas = '';

    public string $prefix_nomor_slip = '';

    public string $prefix_nomor_potongan = '';

    public bool $smtp_enabled = false;

    public string $smtp_host = '';

    public string $smtp_port = '587';

    public string $smtp_username = '';

    public string $smtp_password = '';

    public string $smtp_encryption = 'tls';

    public string $mail_from_address = '';

    public string $mail_from_name = '';

    public function mount(): void
    {
        Gate::authorize('settings.manage');

        $values = Settings::all();
        $this->nama_universitas = $values['nama_universitas'];
        $this->nama_fakultas = $values['nama_fakultas'];
        $this->alamat_fakultas = $values['alamat_fakultas'];
        $this->prefix_nomor_slip = $values['prefix_nomor_slip'];
        $this->prefix_nomor_potongan = $values['prefix_nomor_potongan'];
        $this->smtp_enabled = $values['smtp_enabled'] === '1';
        $this->smtp_host = $values['smtp_host'];
        $this->smtp_port = $values['smtp_port'];
        $this->smtp_username = $values['smtp_username'];
        $this->smtp_encryption = $values['smtp_encryption'];
        $this->mail_from_address = $values['mail_from_address'];
        $this->mail_from_name = $values['mail_from_name'];
        // Password tidak pernah ditampilkan — field dikosongkan = pertahankan yang tersimpan.
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
            'smtp_enabled' => ['boolean'],
            'smtp_host' => ['nullable', 'string', 'max:150'],
            'smtp_port' => ['nullable', 'string', 'max:10'],
            'smtp_username' => ['nullable', 'string', 'max:150'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none', 'max:10'],
            'mail_from_address' => ['nullable', 'email', 'max:150'],
            'mail_from_name' => ['nullable', 'string', 'max:150'],
        ], [
            'prefix_nomor_slip.regex' => 'Format prefix hanya boleh huruf kapital, angka, garis miring, dan strip.',
            'prefix_nomor_potongan.regex' => 'Format prefix hanya boleh huruf kapital, angka, garis miring, dan strip.',
            'smtp_encryption.in' => 'Enkripsi SMTP harus tls, ssl, atau none.',
        ]);

        foreach ($validated as $key => $value) {
            if ($key === 'smtp_enabled') {
                SystemSetting::updateOrCreate(['key' => $key], ['value' => $value ? '1' : '0']);

                continue;
            }

            if ($key === 'smtp_password') {
                // Kosong = pertahankan password yang sudah tersimpan.
                if ($value !== '') {
                    SystemSetting::updateOrCreate(['key' => $key], ['value' => Crypt::encryptString($value)]);
                }

                continue;
            }

            SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        AuditLogger::log('Ubah Pengaturan', 'Mengubah pengaturan kop surat, format nomor dokumen, dan SMTP email.', array_keys($validated));

        session()->flash('status', 'Pengaturan berhasil disimpan.');
    }

    public function testSmtp(): void
    {
        Gate::authorize('settings.manage');

        $this->validate([
            'smtp_host' => ['required', 'string', 'max:150'],
            'smtp_port' => ['required', 'string', 'max:10'],
            'smtp_username' => ['nullable', 'string', 'max:150'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none', 'max:10'],
            'mail_from_address' => ['nullable', 'email', 'max:150'],
            'mail_from_name' => ['nullable', 'string', 'max:150'],
        ], ['smtp_encryption.in' => 'Enkripsi SMTP harus tls, ssl, atau none.']);

        // Pakai nilai form saat ini (belum tentu tersimpan) supaya uji = apa yang diketik.
        Settings::applyMailConfig([
            'smtp_enabled' => '1',
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'smtp_username' => $this->smtp_username,
            'smtp_password' => $this->smtp_password ?: Settings::get('smtp_password'),
            'smtp_encryption' => $this->smtp_encryption,
            'mail_from_address' => $this->mail_from_address,
            'mail_from_name' => $this->mail_from_name,
        ]);

        try {
            Mail::raw(
                'Email uji dari Sistem Administrasi Gaji Fakultas MIPA UNS — konfigurasi SMTP berfungsi dengan baik.',
                fn ($message) => $message->to(auth()->user()->email)->subject('Uji Koneksi SMTP — Sistem Gaji FMIPA')
            );

            session()->flash('status', 'Email uji terkirim ke '.auth()->user()->email.'.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Uji SMTP gagal: '.$e->getMessage());
        }
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

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">SMTP Email (Notifikasi Slip Gaji)</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Konfigurasi server email untuk mengirim slip gaji (§22). Disimpan di aplikasi — tidak perlu mengubah file .env. Setelah mengisi, klik <strong>Uji Koneksi</strong> untuk memastikan SMTP berfungsi.</p>

            <div class="mt-4 space-y-4">
                <label class="flex items-center gap-2">
                    <input wire:model="smtp_enabled" type="checkbox" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-sm text-slate-600 dark:text-slate-300">Aktifkan pengiriman email</span>
                </label>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="smtp_host" value="Host SMTP" />
                        <x-text-input wire:model="smtp_host" id="smtp_host" type="text" class="mt-1 block w-full" placeholder="mis. smtp.gmail.com" />
                        <x-input-error class="mt-2" :messages="$errors->get('smtp_host')" />
                    </div>

                    <div>
                        <x-input-label for="smtp_port" value="Port" />
                        <x-text-input wire:model="smtp_port" id="smtp_port" type="text" inputmode="numeric" class="mt-1 block w-full" placeholder="587 / 465 / 25" />
                        <x-input-error class="mt-2" :messages="$errors->get('smtp_port')" />
                    </div>

                    <div>
                        <x-input-label for="smtp_username" value="Username" />
                        <x-text-input wire:model="smtp_username" id="smtp_username" type="text" autocomplete="off" class="mt-1 block w-full" />
                        <x-input-error class="mt-2" :messages="$errors->get('smtp_username')" />
                    </div>

                    <div>
                        <x-input-label for="smtp_password" value="Password" />
                        <x-text-input wire:model="smtp_password" id="smtp_password" type="password" autocomplete="new-password" class="mt-1 block w-full" placeholder="Kosongkan jika tidak diubah" />
                        <x-input-error class="mt-2" :messages="$errors->get('smtp_password')" />
                    </div>

                    <div>
                        <x-input-label for="smtp_encryption" value="Enkripsi" />
                        <select wire:model="smtp_encryption" id="smtp_encryption" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="tls">TLS (port 587)</option>
                            <option value="ssl">SSL (port 465)</option>
                            <option value="none">Tanpa enkripsi (port 25)</option>
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('smtp_encryption')" />
                    </div>

                    <div>
                        <x-input-label for="mail_from_address" value="Alamat Pengirim (From)" />
                        <x-text-input wire:model="mail_from_address" id="mail_from_address" type="email" class="mt-1 block w-full" placeholder="no-reply@mipa.uns.ac.id" />
                        <x-input-error class="mt-2" :messages="$errors->get('mail_from_address')" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="mail_from_name" value="Nama Pengirim (From Name)" />
                        <x-text-input wire:model="mail_from_name" id="mail_from_name" type="text" class="mt-1 block w-full" placeholder="Sistem Administrasi Gaji FMIPA UNS" />
                        <x-input-error class="mt-2" :messages="$errors->get('mail_from_name')" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-secondary-button type="button" wire:click="testSmtp" wire:loading.attr="disabled" wire:target="testSmtp">
                        <span wire:loading.remove wire:target="testSmtp">Kirim Email Uji ke {{ auth()->user()->email }}</span>
                        <span wire:loading wire:target="testSmtp">Menguji…</span>
                    </x-secondary-button>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Email uji dikirim ke alamat Anda sendiri dengan nilai SMTP yang sedang diketik.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <x-primary-button type="submit">Simpan Pengaturan</x-primary-button>
        </div>
    </form>
</div>
