<?php

namespace App\Services\Salary;

use App\Models\SalaryPeriod;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mengelola siklus hidup Periode Gaji (CLAUDE.md §7 & §17):
 *
 *   DRAFT → VERIFIKASI → FINAL → ARSIP
 *              ↓ (ditolak)
 *            DRAFT
 *
 * Plus mekanisme revisi dari FINAL (menghasilkan versi baru, kembali ke VERIFIKASI).
 *
 * Semua transisi status dibungkus row-lock (`lockForUpdate`) di dalam transaksi
 * DB supaya atomik — mencegah kondisi "setengah final" kalau dua user memproses
 * periode yang sama nyaris bersamaan (CLAUDE.md §17 "Locking Saat Verifikasi &
 * Finalisasi").
 *
 * Validasi bisnis penuh sebelum finalisasi (§16 — NIP valid, potongan tidak
 * negatif, dst.) ditegakkan lewat SalaryPeriodValidationService (STEP 12),
 * dijalankan di dalam row-lock yang sama dengan transisi VERIFIKASI→FINAL
 * supaya cek & commit atomik (tidak ada celah data berubah di antaranya).
 */
class SalaryPeriodService
{
    private const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(
        private readonly SalaryPeriodValidationService $validationService,
    ) {}

    public function create(int $bulan, int $tahun): SalaryPeriod
    {
        if (SalaryPeriod::where('bulan', $bulan)->where('tahun', $tahun)->where('versi', 1)->exists()) {
            throw new \RuntimeException('Periode '.self::NAMA_BULAN[$bulan].' '.$tahun.' sudah ada.');
        }

        $period = SalaryPeriod::create([
            'nama_periode' => self::NAMA_BULAN[$bulan].' '.$tahun,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'status' => SalaryPeriod::STATUS_DRAFT,
            'versi' => 1,
        ]);

        AuditLogger::log('Buat Periode', "Membuat periode {$period->nama_periode}.", ['salary_period_id' => $period->id]);

        return $period;
    }

    public function ajukanVerifikasi(SalaryPeriod $period, User $user): void
    {
        $this->withLock($period, SalaryPeriod::STATUS_DRAFT, 'diajukan verifikasi', function (SalaryPeriod $locked) use ($user) {
            $locked->update([
                'status' => SalaryPeriod::STATUS_VERIFIKASI,
                'locked_by_user_id' => $user->id,
                'locked_at' => now(),
            ]);

            AuditLogger::log('Ajukan Verifikasi', "Mengajukan verifikasi periode {$locked->nama_periode}.", ['salary_period_id' => $locked->id]);
        });
    }

    public function kembalikanKeDraft(SalaryPeriod $period, User $user, string $alasan): void
    {
        $this->withLock($period, SalaryPeriod::STATUS_VERIFIKASI, 'dikembalikan ke draft', function (SalaryPeriod $locked) use ($alasan) {
            $locked->update([
                'status' => SalaryPeriod::STATUS_DRAFT,
                'locked_by_user_id' => null,
                'locked_at' => null,
            ]);

            AuditLogger::log('Kembalikan ke Draft', "Mengembalikan periode {$locked->nama_periode} ke DRAFT. Alasan: {$alasan}", ['salary_period_id' => $locked->id]);
        });
    }

    public function finalisasi(SalaryPeriod $period, User $user): void
    {
        $this->withLock($period, SalaryPeriod::STATUS_VERIFIKASI, 'difinalisasi', function (SalaryPeriod $locked) {
            $errors = $this->validationService->errors($locked);
            if (! empty($errors)) {
                throw new \RuntimeException("Periode belum lolos validasi (§16), tidak bisa difinalisasi:\n- ".implode("\n- ", $errors));
            }

            $locked->update([
                'status' => SalaryPeriod::STATUS_FINAL,
                'locked_by_user_id' => null,
                'locked_at' => null,
            ]);

            AuditLogger::log('Finalisasi Periode', "Memfinalisasi periode {$locked->nama_periode}.", ['salary_period_id' => $locked->id]);
        });
    }

    public function arsipkan(SalaryPeriod $period): void
    {
        $this->withLock($period, SalaryPeriod::STATUS_FINAL, 'diarsipkan', function (SalaryPeriod $locked) {
            $locked->update(['status' => SalaryPeriod::STATUS_ARSIP]);

            AuditLogger::log('Arsipkan Periode', "Mengarsipkan periode {$locked->nama_periode}.", ['salary_period_id' => $locked->id]);
        });
    }

    /**
     * Mengajukan revisi atas periode FINAL: membuat versi baru (n+1) berstatus
     * VERIFIKASI, versi lama ditandai superseded. Belum menyalin salary_records/
     * deduction_records (belum ada datanya sampai STEP 8-11 selesai) — itu
     * ditambahkan saat modul terkait dibangun.
     */
    public function ajukanRevisi(SalaryPeriod $period, User $user, string $alasan): SalaryPeriod
    {
        return $this->withLock($period, SalaryPeriod::STATUS_FINAL, 'direvisi', function (SalaryPeriod $locked) use ($user, $alasan) {
            $versiBaru = SalaryPeriod::create([
                'nama_periode' => $locked->nama_periode,
                'bulan' => $locked->bulan,
                'tahun' => $locked->tahun,
                'status' => SalaryPeriod::STATUS_VERIFIKASI,
                'versi' => $locked->versi + 1,
                'periode_asal_id' => $locked->id,
                'locked_by_user_id' => $user->id,
                'locked_at' => now(),
                'alasan_revisi' => $alasan,
            ]);

            $locked->update(['status_supersede' => true]);

            AuditLogger::log('Ajukan Revisi', "Revisi periode {$locked->nama_periode} versi {$locked->versi} → versi {$versiBaru->versi}. Alasan: {$alasan}", [
                'salary_period_id' => $versiBaru->id,
                'periode_asal_id' => $locked->id,
            ]);

            return $versiBaru;
        });
    }

    /**
     * Menghapus total 1 periode beserta seluruh data turunannya (salary_records,
     * salary_components, deduction_records, salary_imports, deduction_imports —
     * semua sudah cascadeOnDelete lewat FK). Hanya untuk periode DRAFT — periode
     * VERIFIKASI/FINAL/ARSIP tidak boleh dihapus (§17 CLAUDE.md: FINAL bersifat
     * append-only, kesalahan wajib lewat mekanisme revisi, bukan penghapusan).
     *
     * Kalau periode ini adalah hasil revisi (periode_asal_id terisi) yang lalu
     * dibatalkan, periode asal yang tadinya ditandai "digantikan" dikembalikan
     * ke status aktif (status_supersede = false) supaya tidak jadi periode FINAL
     * yatim tanpa versi penerus.
     */
    public function hapusPeriode(SalaryPeriod $period, User $user): void
    {
        $filesToDelete = $this->withLock($period, SalaryPeriod::STATUS_DRAFT, 'dihapus', function (SalaryPeriod $locked) {
            $namaPeriode = $locked->nama_periode;
            $versi = $locked->versi;
            $periodeAsalId = $locked->periode_asal_id;

            $files = [
                ...$locked->salaryImports()->pluck('path_file')->all(),
                ...$locked->deductionImports()->pluck('path_file')->all(),
            ];

            if ($periodeAsalId) {
                SalaryPeriod::whereKey($periodeAsalId)->update(['status_supersede' => false]);
            }

            AuditLogger::log('Hapus Periode', "Menghapus total periode {$namaPeriode} versi {$versi}.".($periodeAsalId ? ' Periode asal dikembalikan ke status aktif (revisi dibatalkan).' : ''), [
                'salary_period_id' => $locked->id,
                'periode_asal_id' => $periodeAsalId,
            ]);

            $locked->delete();

            return $files;
        });

        foreach ($filesToDelete as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    /**
     * Mengunci baris periode (row lock) di dalam transaksi, memuat ulang data
     * terbaru dari DB, memvalidasi statusnya masih sesuai ekspektasi, baru
     * menjalankan mutasi — supaya dua request nyaris bersamaan tidak bisa
     * sama-sama lolos dari status yang sama (mencegah "setengah final").
     */
    private function withLock(SalaryPeriod $period, string $expectedStatus, string $aksi, \Closure $callback): mixed
    {
        return DB::transaction(function () use ($period, $expectedStatus, $aksi, $callback) {
            $locked = SalaryPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== $expectedStatus) {
                throw new \RuntimeException("Periode tidak bisa {$aksi} — status saat ini {$locked->status}, harus {$expectedStatus}.");
            }

            return $callback($locked);
        });
    }
}
