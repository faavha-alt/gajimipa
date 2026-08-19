# Aktor & Hak Akses

Sumber: CLAUDE.md §23 (Hak Akses). Dokumen ini merinci tiap aktor jadi tanggung jawab konkret per modul (§9), sebagai acuan untuk desain Policy/Role Laravel (`spatie/laravel-permission`) di STEP 5.

## 1. Super Admin

**Siapa:** Pengelola teknis sistem (biasanya bagian IT/kepegawaian fakultas).

**Akses:** Penuh ke seluruh modul (§9), termasuk:
- User & Hak Akses (buat/nonaktifkan user, atur role).
- Master Unit, Master Jenis Potongan, Master Status Pegawai (Pengaturan/Master Data).
- Semua yang bisa dilakukan Operator, Verifikator, Pimpinan.
- Audit Log (lihat seluruh histori, semua user).
- Pengaturan sistem.

**Batasan tetap berlaku:** Data periode **FINAL tidak boleh diedit/dihapus langsung, termasuk oleh Super Admin** (§17 CLAUDE.md — append-only). Super Admin hanya bisa memulai mekanisme revisi resmi, bukan bypass langsung.

## 2. Operator Gaji

**Siapa:** Staf yang menjalankan proses bulanan (upload data pusat, input potongan, jalankan proses gaji).

**Akses:**
- Master Pegawai: CRUD (§11).
- Import Gaji Pusat: upload, mapping, validasi, preview, konfirmasi (§12).
- Import Potongan: upload, mapping, validasi, preview, konfirmasi (§13).
- Data Potongan: input manual, edit selama periode masih DRAFT (§14).
- Proses Gaji: jalankan penggabungan data pusat + potongan (§15).
- Dokumen: generate slip gaji, bukti potongan (§18, §19) — untuk periode FINAL.
- Laporan: lihat & export (§21).

**Tidak bisa:** Verifikasi/finalisasi periode (§17), kelola user/role, kelola Master Unit/Master Jenis Potongan (kecuali diberi izin tambahan oleh Super Admin — perlu diputuskan saat desain permission granular).

## 3. Verifikator

**Siapa:** Pihak yang memeriksa kebenaran data sebelum disahkan (mis. Kasubbag Keuangan).

**Akses:**
- Melihat seluruh data periode berjalan (read-only atas data yang di-input Operator).
- Verifikasi periode: DRAFT → VERIFIKASI (§7, §17).
- Finalisasi periode: VERIFIKASI → FINAL (§17).
- Melihat & menyetujui pengajuan revisi (§17 "Mekanisme Revisi").

**Tidak bisa:** Mengedit data penghasilan/potongan secara langsung (hanya bisa menolak dan meminta Operator memperbaiki selama masih DRAFT), generate/kirim dokumen ke pegawai.

**Locking:** Saat Verifikator sedang memproses sebuah periode (status VERIFIKASI / proses finalisasi), periode tersebut terkunci untuk Operator lain — lihat CLAUDE.md §17 "Locking Saat Verifikasi & Finalisasi".

## 4. Pimpinan

**Siapa:** Dekan/Wakil Dekan atau pihak manajemen yang butuh visibilitas, bukan operasional.

**Akses:**
- Dashboard (§10) — read-only.
- Laporan bulanan & tahunan (§21) — lihat & export.
- Data periode FINAL — read-only, termasuk slip/bukti potongan seluruh pegawai bila diperlukan untuk pengawasan.
- Approval, **jika suatu saat dibutuhkan** (§23 menyebut ini kondisional — belum ada langkah approval eksplisit di alur §17; perlu diklarifikasi apakah Pimpinan punya peran approval formal antara VERIFIKASI dan FINAL, atau perannya murni pengawasan).

**Tidak bisa:** Mengubah data apa pun (operasional maupun master data).

## 5. Pegawai

**Siapa:** Seluruh pegawai FMIPA yang datanya diproses sistem (dosen & tendik).

**Akses (self-service, terbatas ke data milik sendiri):**
- Lihat & download slip gaji sendiri (§18).
- Lihat & download bukti potongan sendiri (§19).
- Lihat histori gaji sendiri (§10 versi personal — bukan dashboard agregat).

**Tidak bisa:** Melihat data pegawai lain dalam bentuk apa pun (termasuk melalui laporan/rekap), mengubah data apa pun.

**Catatan identifikasi (dari `docs/excel-gaji-pusat.md`):** Login/akses pegawai idealnya dikaitkan ke NIP sebagai identifier utama, bukan nama (nama bisa kembar). Field `email` di Master Pegawai (§11) dipakai baik untuk notifikasi (§22) maupun kemungkinan sebagai kredensial login pegawai — perlu diputuskan di STEP 5 apakah pegawai login pakai email atau NIP.

## Ringkasan Matriks (indikatif, detail permission granular ditentukan saat implementasi §23)

| Modul | Super Admin | Operator | Verifikator | Pimpinan | Pegawai |
|---|:---:|:---:|:---:|:---:|:---:|
| Dashboard | ✓ | ✓ | ✓ | ✓ | — (versi personal) |
| Master Pegawai/Unit | ✓ | ✓ (pegawai) | lihat | lihat | — |
| Master Jenis Potongan | ✓ | — | — | — | — |
| Periode Gaji (buat/buka) | ✓ | ✓ | lihat | lihat | — |
| Import Gaji Pusat | ✓ | ✓ | lihat | — | — |
| Import/Input Potongan | ✓ | ✓ | lihat | — | — |
| Proses Gaji | ✓ | ✓ | lihat | — | — |
| Verifikasi & Finalisasi | ✓ | — | ✓ | lihat | — |
| Slip Gaji (generate massal) | ✓ | ✓ | — | — | lihat milik sendiri |
| Bukti Potongan (generate massal) | ✓ | ✓ | — | — | lihat milik sendiri |
| Rekap Setoran | ✓ | ✓ | lihat | lihat | — |
| Laporan | ✓ | ✓ | lihat | ✓ | — |
| Notifikasi Email | ✓ | ✓ (trigger/resend) | — | — | terima email |
| User & Hak Akses | ✓ | — | — | — | — |
| Audit Log | ✓ | lihat aktivitas sendiri | — | — | — |
| Pengaturan | ✓ | — | — | — | — |

## Pertanyaan terbuka (perlu dikonfirmasi ke fakultas)

1. Apakah Pimpinan punya langkah **approval formal** dalam alur VERIFIKASI→FINAL, atau perannya murni read-only pengawasan (§23 menyebut "Approval jika diperlukan" tanpa detail)?
2. Apakah Pegawai login pakai NIP atau email sebagai kredensial utama?
3. Apakah Operator boleh melihat/mengubah Master Unit & Master Jenis Potongan, atau itu eksklusif Super Admin?
