# Requirements — Sistem Administrasi Gaji Fakultas MIPA UNS

Sumber utama: CLAUDE.md (spesifikasi lengkap). Dokumen ini merangkum kebutuhan dalam format requirements formal, plus status ketersediaan info per STEP 1 (CLAUDE.md §31). Rincian aktor lihat `docs/actors.md`, rincian alur lihat `docs/workflow.md`, rincian struktur Excel lihat `docs/excel-gaji-pusat.md` & `docs/excel-potongan.md`.

## 1. Tujuan & Ruang Lingkup

Aplikasi internal administrasi gaji, **bukan** sistem payroll banking. Fungsi: mencatat, mengolah, mendokumentasikan, melaporkan data gaji pegawai FMIPA UNS berdasarkan data penghasilan dari pusat + data potongan dikelola fakultas. Detail batas termasuk/tidak termasuk: CLAUDE.md §6.

## 2. Functional Requirements

### FR-1 Master Data
- FR-1.1 CRUD Master Pegawai (NIP sebagai identifier utama, Unit & Status Pegawai dari master dinamis) — §11.
- FR-1.2 CRUD Master Unit — §11.1.
- FR-1.3 CRUD Master Status Pegawai (dinamis, bukan hardcode) — §11.
- FR-1.4 CRUD Master Jenis Potongan (dinamis, bukan hardcode) — §4.
- FR-1.5 (Baru, dari analisis Excel) Mapping identifier internal fakultas (NPP) ↔ NIP, untuk mendukung import potongan yang sumbernya tidak menyertakan NIP — lihat `docs/excel-potongan.md` §5. **Belum ada di CLAUDE.md, perlu konfirmasi ke fakultas apakah ini diadopsi.**

### FR-2 Periode Gaji
- FR-2.1 Create/buka periode gaji per bulan.
- FR-2.2 Transisi status DRAFT → VERIFIKASI → FINAL → ARSIP, dengan locking saat VERIFIKASI/proses finalisasi — §7, §17.
- FR-2.3 Mekanisme revisi untuk data FINAL (append-only, versi baru) — §17.

### FR-3 Import Data Pusat
- FR-3.1 Upload Excel → deteksi struktur → mapping kolom → validasi → preview → konfirmasi → import → snapshot — §12.
- FR-3.2 Import all-or-nothing per periode; error ditampilkan per baris.
- FR-3.3 Snapshot menyimpan metadata: periode, sumber, file, tanggal upload, uploader, status — §8.
- FR-3.4 Validasi total penghasilan/potongan/bersih memakai rumus terverifikasi — `docs/excel-gaji-pusat.md` §3.

### FR-4 Import & Input Data Potongan
- FR-4.1 Upload Excel dengan mapping kolom→jenis potongan (mendukung banyak jenis potongan sekaligus dalam satu file — temuan `docs/excel-potongan.md` §6, revisi dari asumsi awal §13).
- FR-4.2 Input potongan manual per pegawai/jenis/periode — §14.
- FR-4.3 Import all-or-nothing per periode & per jenis potongan — §13.
- FR-4.4 Semua perubahan (manual maupun import) tercatat di Audit Log.

### FR-5 Proses Gaji
- FR-5.1 Gabungkan data pusat + potongan → Total Penghasilan, Total Potongan, Gaji Bersih per pegawai — §15.
- FR-5.2 Basis "Total Penghasilan" = kolom `bersih` dari data pusat (setelah potongan pusat: PFK/PPh/BPJS), bukan penjumlahan komponen mentah — temuan `docs/excel-gaji-pusat.md` §4. **Perlu konfirmasi final ke fakultas sebelum dikunci sebagai aturan sistem.**
- FR-5.3 Komponen penghasilan (gaji pokok, tiap jenis tunjangan) tetap disimpan granular untuk ditampilkan di slip — §29 (histori per komponen).

### FR-6 Validasi & Finalisasi
- FR-6.1 Checklist validasi pra-finalisasi (periode lengkap, NIP valid, tidak duplikat, nominal valid, dst.) — §16.
- FR-6.2 Finalisasi atomik, tidak ada kondisi "setengah final" — §17.
- FR-6.3 Data FINAL tidak dapat diedit/dihapus biasa oleh siapa pun termasuk Super Admin — §17.

### FR-7 Dokumen
- FR-7.1 Generate Slip Gaji PDF (individual & massal), preview, download, cetak, histori — §18.
- FR-7.2 Generate Bukti Potongan PDF (individual & massal, per jenis potongan) dengan nomor dokumen berurutan otomatis per periode — §19.
- FR-7.3 Dokumen hasil revisi diterbitkan sebagai dokumen baru dengan penanda "Revisi" — §17.

### FR-8 Rekap & Laporan
- FR-8.1 Rekap Setoran Potongan per jenis (jumlah pegawai, total), export PDF/Excel — §20.
- FR-8.2 Laporan Bulanan: rekap gaji, potongan, netto, per unit, per jenis potongan, daftar pegawai — §21.
- FR-8.3 Laporan Tahunan: rekap seluruh periode, perbandingan antarbulan — §21.
- FR-8.4 Semua laporan bisa difilter & diekspor.

### FR-9 Notifikasi Email
- FR-9.1 Kirim slip via email otomatis setelah periode FINAL & slip tersedia — §22.
- FR-9.2 Status pengiriman: BELUM DIKIRIM / TERKIRIM / GAGAL / DIKIRIM ULANG, tersimpan histori — §22.

### FR-10 Dashboard
- FR-10.1 Ringkasan periode aktif: jumlah pegawai, total penghasilan/potongan/bersih, status periode/import/verifikasi/email — §10.
- FR-10.2 Grafik penghasilan/potongan per bulan, rekap jenis potongan, rekap unit, histori periode.

### FR-11 Hak Akses & Keamanan
- FR-11.1 Role: Super Admin, Operator Gaji, Verifikator, Pimpinan, Pegawai — detail di `docs/actors.md`.
- FR-11.2 Pegawai hanya bisa akses data miliknya sendiri (slip, bukti potongan, histori) — §23.
- FR-11.3 Audit Log mencatat aktivitas: login, import, edit, hapus, verifikasi, finalisasi, revisi, generate dokumen, email — §24.

## 3. Non-Functional Requirements

- NFR-1 **Histori wajib** — semua nilai gaji tersimpan per periode, tidak ada overwrite nilai lama (§29).
- NFR-2 **Tipe data nominal** — `decimal(15,2)` atau `bigint` satuan rupiah penuh; `float`/`double` dilarang (§29).
- NFR-3 **Timezone** Asia/Jakarta (WIB) untuk seluruh timestamp; **bahasa** Indonesia untuk seluruh UI/email/PDF; **format Rp** `Rp 10.000.000` (§25).
- NFR-4 **Keamanan**: autentikasi & otorisasi wajib, audit log wajib, validasi upload file, akses data dibatasi per role, backup database & dokumen, HTTPS di production, tidak menyimpan password plaintext / credential di Git (§30). Termasuk: **jangan commit data pribadi/keuangan pegawai** (NIP, rekening, NPWP, gaji) — lihat `.gitignore`.
- NFR-5 **Arsitektur**: pemisahan Controller/Livewire → Action/Service → Model → Database, tidak ada logic bisnis besar di Livewire Component (§26, §27).
- NFR-6 **Testing**: Pest, minimal Unit/Feature/Import/Calculation/Permission/PDF/Email/Security Test, kasus tepi dari data riil (mis. pegawai pensiun mid-periode) ditambahkan ke test cases §19 CLAUDE.md.
- NFR-7 **Queue** untuk proses berat: generate PDF massal, kirim email massal, import besar (§25).
- NFR-8 **Stack**: Laravel + PHP + MySQL, Blade + Livewire + Tailwind + Alpine.js, tanpa Filament/admin panel siap pakai, role/permission via `spatie/laravel-permission` dengan UI manual (§25).

## 4. Data Sumber (ringkasan — detail lengkap di dokumen terpisah)

| Sumber | Dokumen detail | Status |
|---|---|---|
| Excel Gaji Pusat | `docs/excel-gaji-pusat.md` | Draft, berbasis 1 contoh file, ada pertanyaan terbuka |
| Excel Potongan Fakultas | `docs/excel-potongan.md` | Draft, berbasis 1 contoh file, ada pertanyaan terbuka |
| Database final | `docs/database.md` | **Belum dibuat** — menunggu jawaban pertanyaan terbuka di kedua dokumen Excel (STEP 4, CLAUDE.md §31) |

## 5. Constraint & Prinsip Wajib (ringkasan dari CLAUDE.md §32)

1. Kerjakan bertahap per STEP, jangan sekaligus.
2. Baca dokumentasi (`docs/*`) sebelum coding.
3. Jangan ubah arsitektur tanpa alasan terdokumentasi.
4. Jangan asumsikan format Excel di luar yang sudah dianalisis — perluasan mapping hanya setelah ada contoh nyata.
5. Database final dibuat setelah analisis Excel selesai dikonfirmasi (belum — lihat §4 tabel di atas).
6. Semua logic bisnis (kalkulasi gaji, potongan, import, finalisasi) wajib punya test.
7. Setiap STEP harus meninggalkan aplikasi dalam kondisi bisa dijalankan.
8. Dokumentasi diperbarui setiap struktur sistem berubah.

## 6. Status & Langkah Berikutnya

STEP 1 (dokumen ini + `docs/actors.md` + `docs/workflow.md`) dan STEP 2–3 (analisis Excel) **selesai sebagai draft**. Sebelum STEP 4 (finalisasi database) dan implementasi (STEP 5 dst.) dimulai, seluruh **pertanyaan terbuka** di `docs/excel-gaji-pusat.md` §6, `docs/excel-potongan.md` §9, dan `docs/actors.md` bagian penutup perlu dikonfirmasi ke pihak Fakultas MIPA UNS. Lihat `PROGRESS.md` untuk checklist terkini.
