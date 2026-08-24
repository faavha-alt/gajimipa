# Sistem Administrasi Gaji Fakultas MIPA UNS

## 1. Gambaran Umum

Sistem Administrasi Gaji Fakultas MIPA UNS adalah aplikasi internal Fakultas MIPA UNS untuk mencatat, mengolah, mendokumentasikan, dan melaporkan data gaji pegawai berdasarkan:

1. Data penghasilan yang diberikan oleh pusat.
2. Data potongan yang dikelola oleh Fakultas MIPA.

Sistem ini **bukan sistem payroll perbankan**.

Sistem tidak melakukan transfer gaji ke rekening pegawai dan tidak terhubung dengan sistem perbankan.

Sistem hanya digunakan untuk kebutuhan administrasi, pencatatan, pengolahan data, pembuatan dokumen, dan pelaporan di lingkungan Fakultas MIPA UNS.

---

# 2. Tujuan Sistem

Sistem dibuat untuk:

* Mempermudah pencatatan gaji pegawai.
* Mengurangi pekerjaan manual dari file Excel.
* Menggabungkan data gaji pusat dengan data potongan fakultas.
* Mengurangi kesalahan perhitungan.
* Menyediakan histori gaji setiap pegawai.
* Membuat slip gaji secara otomatis.
* Membuat bukti potongan.
* Membuat rekap setoran potongan.
* Membuat laporan bulanan dan tahunan.
* Mengirim notifikasi slip melalui email.
* Menyediakan audit trail setiap perubahan data.

---

# 3. Prinsip Utama

## 3.1 Data Penghasilan Berasal dari Pusat

Pusat memberikan data gaji dalam bentuk Excel.

Data dapat berisi:

* NIP
* Nama
* Gaji pokok
* Tunjangan
* Komponen penghasilan lainnya
* Total penghasilan

Format sebenarnya akan ditentukan setelah contoh Excel diberikan.

Data dari pusat dianggap sebagai sumber resmi penghasilan.

Sistem tidak boleh mengubah nilai sumber secara langsung tanpa mekanisme koreksi dan audit.

---

# 4. Data Potongan

Fakultas dapat menerima atau memasukkan data potongan dari berbagai sumber.

Contoh:

* Koperasi
* Pinjaman
* BPJS
* Pajak
* Iuran
* Potongan lainnya

Jenis potongan harus dibuat dinamis melalui master data.

Contoh:

```text
KOPERASI
PINJAMAN
BPJS
PAJAK
IURAN
LAINNYA
```

Sistem tidak boleh melakukan hard-code terhadap jenis potongan.

---

# 5. Perhitungan

Konsep utama:

```text
Total Penghasilan
-
Total Potongan
=
Gaji Bersih
```

Contoh:

```text
Total Penghasilan : Rp 10.000.000
Total Potongan    : Rp 1.500.000
Gaji Bersih       : Rp  8.500.000
```

Total penghasilan berasal dari data pusat.

Total potongan berasal dari data yang dikelola oleh Fakultas.

---

# 6. Batasan Sistem

## Termasuk

* Master pegawai
* Periode gaji
* Import Excel gaji pusat
* Import Excel potongan
* Input potongan manual
* Validasi data
* Penggabungan data
* Perhitungan gaji bersih
* Slip gaji
* Bukti potongan
* Rekap setoran potongan
* Laporan bulanan
* Laporan tahunan
* Histori gaji
* Notifikasi email
* Audit log
* Export Excel
* Export PDF
* User management
* Role & permission

## Tidak termasuk

* Transfer gaji ke rekening
* Pembayaran gaji
* Integrasi banking
* Internet banking
* Payment gateway
* Payroll banking
* Rekonsiliasi transaksi bank

Sistem hanya menghasilkan data dan dokumen administrasi.

---

# 7. Periode Gaji

Setiap transaksi wajib memiliki periode.

Contoh:

```text
Januari 2026
Februari 2026
Maret 2026
...
Agustus 2026
```

Status periode:

```text
DRAFT
   ↓
VERIFIKASI
   ↓
FINAL
   ↓
ARSIP
```

## DRAFT

Data dapat diperbaiki.

## VERIFIKASI

Data sedang diperiksa.

## FINAL

Data telah disahkan.

Data FINAL tidak boleh diedit secara langsung.

Jika terjadi kesalahan, harus menggunakan mekanisme koreksi/revisi.

## ARSIP

Periode telah selesai dan menjadi histori.

---

# 8. Snapshot Data Pusat

Setiap import Excel pusat harus menghasilkan snapshot.

Contoh:

```text
Periode       : Agustus 2026
Sumber        : Pusat
File          : gaji_agustus_2026.xlsx
Tanggal Upload: 19 Agustus 2026
Uploaded By   : Operator
Status        : Terverifikasi
```

Data periode sebelumnya tidak boleh berubah akibat import periode berikutnya.

---

# 9. Modul Sistem

Sistem terdiri dari:

```text
1. Dashboard
2. Master Pegawai
3. Master Unit
4. Periode Gaji
5. Import Gaji Pusat
6. Master Jenis Potongan
7. Data Potongan
8. Proses Gaji
9. Verifikasi & Finalisasi
10. Slip Gaji
11. Bukti Potongan
12. Rekap Setoran Potongan
13. Laporan
14. Notifikasi Email
15. User & Hak Akses
16. Audit Log
17. Pengaturan
```

---

# 10. Dashboard

Dashboard periode aktif menampilkan:

```text
Periode Aktif
Jumlah Pegawai
Total Penghasilan
Total Potongan
Total Gaji Bersih
Status Periode
Status Import
Status Verifikasi
Status Email
```

Tambahan:

* Grafik penghasilan per bulan.
* Grafik potongan.
* Rekap jenis potongan.
* Rekap unit.
* Histori periode.

---

# 11. Master Pegawai

Data minimal:

```text
NIP
Nama
Unit
Status Pegawai
Email
Status Aktif
```

Jika terdapat kebutuhan lain, field dapat ditambahkan setelah analisis data pusat.

NIP digunakan sebagai identifier utama.

Nama tidak boleh digunakan sebagai identifier utama karena nama dapat sama.

`Unit` dan `Status Pegawai` bukan nilai bebas (free text) dan bukan enum tetap di kode.

Keduanya diambil dari master data dinamis, dengan pola yang sama seperti Master Jenis Potongan (lihat §4):

* `Unit` → Master Unit (§11.1).
* `Status Pegawai` → Master Status Pegawai, dikelola melalui halaman Pengaturan/Master Data.

Alasan: struktur unit dan kategori status pegawai di lingkungan PTN-BH (mis. PNS, PPPK, Non-PNS, Dosen, Tendik) dapat berbeda-beda dan berubah dari waktu ke waktu. Nilai pastinya **tidak boleh diasumsikan atau di-hardcode** sebelum data pusat dianalisis — sistem hanya menyediakan mekanismenya.

## 11.1 Master Unit

Data minimal:

```text
Kode Unit
Nama Unit
Status Aktif
```

Contoh (indikatif, bukan final):

```text
PRODI MATEMATIKA
PRODI FISIKA
PRODI KIMIA
PRODI BIOLOGI
PRODI INFORMATIKA
TATA USAHA
```

Master Unit dipakai oleh Master Pegawai dan menjadi dasar filter/rekap "per unit" di Dashboard (§10) dan Laporan (§21).

Sistem tidak boleh melakukan hard-code terhadap daftar unit.

---

# 12. Import Gaji Pusat

Alur:

```text
Upload Excel
      ↓
Deteksi Struktur
      ↓
Mapping Kolom
      ↓
Validasi
      ↓
Preview
      ↓
Konfirmasi
      ↓
Import
      ↓
Snapshot
```

Validasi minimal:

* NIP tidak ditemukan.
* NIP duplikat.
* Data kosong.
* Nominal tidak valid.
* Data bukan angka.
* Komponen tidak dikenali.
* Pegawai tidak aktif.
* Periode sudah memiliki data.
* Total tidak sesuai.

Sistem harus menampilkan error per baris sehingga operator mudah melakukan koreksi.

Import bersifat **all-or-nothing per periode**: jika masih ada satu baris yang gagal validasi, seluruh proses import untuk periode tersebut ditolak dan tidak ada data yang masuk sebagian. Operator harus memperbaiki seluruh error (langsung di Excel atau melalui koreksi pada layar Preview) sampai semua baris lolos validasi, baru import dapat dikonfirmasi.

---

# 13. Import Potongan

Alur:

```text
Upload Excel
      ↓
Pilih Jenis Potongan
      ↓
Mapping Kolom
      ↓
Validasi
      ↓
Preview
      ↓
Konfirmasi
      ↓
Import
```

Data minimal:

```text
NIP
Jenis Potongan
Periode
Nominal
Keterangan
```

Format Excel setiap jenis potongan dapat berbeda.

Karena itu sistem harus menyediakan mekanisme mapping.

Sama seperti Import Gaji Pusat (§12), import bersifat **all-or-nothing per periode dan per jenis potongan**: baris invalid membatalkan seluruh batch import tersebut sampai dikoreksi.

---

# 14. Potongan Manual

Operator dapat memasukkan potongan secara manual.

Data:

```text
Pegawai
Jenis Potongan
Periode
Nominal
Keterangan
```

Semua perubahan harus tercatat dalam audit log.

---

# 15. Proses Gaji

Sistem menggabungkan:

```text
DATA PUSAT
+
DATA POTONGAN
```

Hasil:

```text
Total Penghasilan
Total Potongan
Gaji Bersih
```

Contoh:

```text
Pegawai A

PENGHASILAN
Gaji Pokok       Rp 7.000.000
Tunjangan        Rp 3.000.000
Total            Rp10.000.000

POTONGAN
Koperasi         Rp   500.000
BPJS             Rp   300.000
Pajak            Rp   700.000
Total            Rp 1.500.000

GAJI BERSIH      Rp 8.500.000
```

---

# 16. Validasi

Sebelum finalisasi:

```text
✓ Semua data memiliki periode
✓ NIP valid
✓ Tidak ada NIP duplikat
✓ Nominal valid
✓ Potongan memiliki pegawai
✓ Tidak ada nominal negatif
✓ Total penghasilan valid
✓ Total potongan valid
✓ Gaji bersih dapat dihitung
```

Jika terdapat error, sistem tidak mengizinkan finalisasi.

---

# 17. Finalisasi

Alur:

```text
DRAFT
 ↓
VERIFIKASI
 ↓
FINAL
```

Ketika FINAL:

* Data terkunci.
* Tidak dapat diedit biasa.
* Tidak dapat dihapus biasa.
* Slip dapat dibuat.
* Laporan dapat dibuat.
* Email dapat dikirim.

Koreksi harus menggunakan mekanisme revisi.

## Locking Saat Verifikasi & Finalisasi

Untuk mencegah dua operator/verifikator memproses periode yang sama secara bersamaan:

* Saat sebuah periode masuk status VERIFIKASI atau sedang difinalisasi, periode tersebut dikunci untuk edit oleh user lain.
* Sistem menampilkan indikator "sedang diproses oleh [nama user]" jika periode terkunci.
* Lock dilepas otomatis setelah proses verifikasi/finalisasi selesai atau dibatalkan.
* Operasi finalisasi harus atomik (tidak boleh ada kondisi periode "setengah final").

## Mekanisme Revisi

Data yang sudah FINAL tidak pernah diedit atau dihapus langsung (append-only), termasuk oleh Super Admin.

Alur revisi:

```text
FINAL
 ↓
Ajukan Revisi (alasan wajib diisi)
 ↓
Salinan data periode dibuat sebagai versi baru (versi ke-n+1)
 ↓
Versi baru kembali ke status VERIFIKASI
 ↓
Diperiksa & difinalisasi ulang
 ↓
Versi lama ditandai "digantikan" (superseded), tetap tersimpan untuk histori/audit
```

Ketentuan:

* Versi data yang sudah pernah dikirim ke pegawai (slip, bukti potongan) tidak dihapus — tetap dapat diakses sebagai arsip, namun ditandai sebagai versi lama.
* Slip/bukti potongan hasil revisi diterbitkan sebagai dokumen baru dan (jika perlu) dikirim ulang via email dengan penanda "Revisi".
* Setiap revisi wajib dicatat di Audit Log (§24): siapa yang mengajukan, alasan, dan apa yang berubah.

---

# 18. Slip Gaji

Sistem menghasilkan PDF.

Isi:

```text
FAKULTAS MIPA UNS

SLIP GAJI
Nomor Dokumen
Periode

Nama
NIP
Unit

PENGHASILAN
Gaji Pokok
Tunjangan
Komponen lainnya
Total Penghasilan

POTONGAN
Koperasi
BPJS
Pajak
Potongan lainnya
Total Potongan

GAJI BERSIH
```

Fitur:

* Preview.
* Download.
* Cetak.
* Generate individual.
* Generate massal.
* Histori slip.

---

# 19. Bukti Potongan

Bukti potongan individual:

```text
BUKTI POTONGAN

Nomor Dokumen
Nama
NIP
Periode
Jenis Potongan
Nominal
Keterangan
```

Output PDF.

`Nomor Dokumen` dibuat otomatis oleh sistem dengan format berurutan per periode (mis. `SLIP/MIPA/VIII/2026/0001`, format final ditentukan bersama pihak fakultas), digunakan sebagai referensi arsip administrasi.

---

# 20. Rekap Setoran Potongan

Sistem tidak melakukan transfer atau pembayaran.

Sistem hanya membuat rekap jumlah potongan yang akan digunakan untuk proses setoran di luar aplikasi.

Contoh:

```text
REKAP SETORAN POTONGAN
Agustus 2026

Koperasi
Jumlah Pegawai : 43
Total          : Rp 12.500.000

BPJS
Jumlah Pegawai : 185
Total          : Rp 35.200.000

Pinjaman
Jumlah Pegawai : 27
Total          : Rp 8.750.000
```

Output:

* PDF
* Excel

---

# 21. Laporan

## Bulanan

* Rekap gaji seluruh pegawai.
* Total penghasilan.
* Total potongan.
* Total gaji bersih.
* Rekap potongan.
* Rekap per unit.
* Rekap setoran.
* Daftar pegawai.

## Tahunan

* Rekap seluruh periode.
* Total penghasilan tahunan.
* Total potongan tahunan.
* Total gaji bersih tahunan.
* Perbandingan antarbulan.

Semua laporan dapat difilter dan diekspor.

---

# 22. Notifikasi Email

Setelah periode FINAL dan slip tersedia:

```text
FINAL
 ↓
Generate Slip
 ↓
Email Pegawai
 ↓
Catat Log
```

Status:

```text
BELUM DIKIRIM
TERKIRIM
GAGAL
DIKIRIM ULANG
```

Sistem harus menyimpan histori email.

---

# 23. Hak Akses

## Super Admin

Akses penuh.

## Operator Gaji

* Master pegawai.
* Import gaji.
* Import potongan.
* Input potongan.
* Proses gaji.
* Dokumen.
* Laporan.

## Verifikator

* Melihat data.
* Verifikasi.
* Finalisasi.

## Pimpinan

* Dashboard.
* Laporan.
* Data final.
* Approval jika diperlukan.

## Pegawai

Hanya dapat:

* Melihat slip sendiri.
* Download slip sendiri.
* Melihat histori sendiri.
* Melihat bukti potongan sendiri.

---

# 24. Audit Log

Aktivitas penting dicatat:

```text
User
Tanggal
Waktu
Aktivitas
Data
IP Address
```

Contoh:

```text
Operator
19-08-2026 10:32
Import Gaji
Periode Agustus 2026
File: gaji_agustus.xlsx
```

Aktivitas:

* Login.
* Import.
* Edit.
* Hapus.
* Verifikasi.
* Finalisasi.
* Revisi.
* Generate dokumen.
* Email.

---

# 25. Teknologi

## Backend

```text
Laravel
PHP
MySQL
```

## Frontend

```text
Laravel Blade
Livewire
Tailwind CSS
Alpine.js
```

Tidak menggunakan Filament.

Tidak menggunakan admin panel siap pakai.

UI dan workflow dibuat secara manual agar sesuai kebutuhan Fakultas MIPA.

## Role & Permission

```text
spatie/laravel-permission
```

Package ini hanya mengelola relasi role/permission di database (tabel `roles`, `permissions`, `model_has_roles`, dst). UI pengelolaan role & permission tetap dibuat manual dengan Livewire, bukan admin panel siap pakai — sehingga tidak melanggar aturan "tidak menggunakan Filament / admin panel siap pakai" di atas.

## Testing

```text
Pest
```

Digunakan untuk seluruh jenis test pada §19 (Unit Test, Feature Test, Import Test, Calculation Test, dst).

## Timezone & Lokalisasi

```text
Timezone   : Asia/Jakarta (WIB)
Bahasa     : Bahasa Indonesia (seluruh UI, email, dan dokumen PDF)
Format Rp  : Rp 10.000.000 (titik sebagai pemisah ribuan, tanpa desimal untuk rupiah penuh)
```

Semua timestamp (audit log, email log, tanggal upload) disimpan dan ditampilkan dalam WIB.

## Import

```text
Laravel Excel
```

## PDF

Gunakan library PDF yang kompatibel dengan Laravel.

## Email

SMTP + Laravel Mail/Notifications.

## Queue

Laravel Queue digunakan untuk proses seperti:

* Generate banyak PDF.
* Pengiriman email massal.
* Proses import besar.

## Deployment

```text
Docker
Linux
MySQL
Nginx
```

Server dapat menggunakan environment yang sudah tersedia di CasaOS.

---

# 26. Prinsip Arsitektur Kode

Jangan menempatkan seluruh logic di Controller atau Livewire Component.

Gunakan pemisahan:

```text
Controller / Livewire
        ↓
Action / Service
        ↓
Model
        ↓
Database
```

Contoh:

```text
SalaryCalculationService
```

bertanggung jawab menghitung:

```text
Total Penghasilan
-
Total Potongan
=
Gaji Bersih
```

Livewire hanya menangani interaksi UI.

---

# 27. Struktur Folder

Target struktur:

```text
app/
├── Actions/
│   ├── Salary/
│   ├── Deduction/
│   ├── Import/
│   └── Report/
│
├── Services/
│   ├── Salary/
│   ├── Deduction/
│   ├── Import/
│   ├── Payslip/
│   └── Report/
│
├── Models/
│
├── Livewire/
│   ├── Dashboard/
│   ├── Employees/
│   ├── Salary/
│   ├── Deductions/
│   ├── Imports/
│   ├── Payslips/
│   └── Reports/
│
├── Policies/
├── Jobs/
├── Notifications/
└── Support/
```

## 27.1 Pola & Komponen Bersama (dipakai silang-halaman)

Halaman Master Data CRUD sederhana (Unit, Status Pegawai, Golongan, Jabatan
Fungsional, Bank, Jenis Potongan) memakai base class bersama:

```text
app/Livewire/Base/SimpleCrud.php
```

Subclass Volt (mis. `pages/units/index.blade.php`) cukup mendeklarasikan
properti form (`public string $kode_unit = ''`) + konfigurasi
(`permission()`, `model()`, `label()`, `formFields()`, `rules()`,
`searchColumns()`, `orderByColumn()`, `listKey()`, `displayColumn()`,
`deleteGuard()`, `pageSize()`). CRUD (openCreate/openEdit/save/toggleActive/
delete/search/paginate) ditangani base. **Halaman master sederhana baru wajib
memakai pola ini, bukan menyalin CRUD manual.**

Komponen Blade bersama (`resources/views/components/`):

```text
x-modal-crud   — dialog aksesibel (role=dialog, aria-modal, focus trap, Esc,
                 body-lock, tombol X); `show` = nama properti Livewire
                 (mis. show="showModal"); entangle via $wire.entangle().
x-flash        — pesan flash session('status')/session('error') dgn role
                 status/alert.
```

Aturan: modal/dialog baru pakai `x-modal-crud`; pesan flash pakai `x-flash`
(bukan markup manual yang terduplikasi).

---

# 28. Database Awal

Struktur awal:

```text
users
roles / permissions (dikelola oleh spatie/laravel-permission)

employees
units

salary_periods

salary_imports
salary_import_rows

salary_records
salary_components

deduction_types
deduction_imports
deduction_records

payslips
deduction_receipts

submission_records

email_logs
audit_logs

system_settings
```

Struktur final **belum boleh dibuat sebelum format Excel pusat dan potongan dianalisis**.

---

# 29. Prinsip Database

Data harus menyimpan histori.

Jangan hanya menyimpan:

```text
pegawai.gaji
```

karena nilai gaji dapat berubah setiap periode.

Gunakan:

```text
pegawai
    ↓
periode
    ↓
salary record
    ↓
salary components
```

Sehingga histori tetap tersedia.

Contoh:

```text
Pegawai A

Januari 2026
Total Penghasilan Rp 9.000.000

Februari 2026
Total Penghasilan Rp 9.500.000

Maret 2026
Total Penghasilan Rp10.000.000
```

## Tipe Data Nominal

Seluruh kolom nominal (gaji, tunjangan, potongan, gaji bersih) wajib menggunakan tipe `decimal` (mis. `decimal(15,2)`) atau `bigint` (integer dalam satuan rupiah penuh).

`float` / `double` **dilarang** untuk kolom nominal karena berisiko menyebabkan pembulatan yang tidak presisi, yang fatal untuk data keuangan.

---

# 30. Keamanan

Karena sistem menyimpan data keuangan pegawai:

* Authentication wajib.
* Role & permission wajib.
* Authorization wajib.
* Audit log wajib.
* File upload harus divalidasi.
* File Excel harus dibatasi.
* Data slip harus dilindungi.
* Pegawai hanya dapat melihat datanya sendiri.
* Database harus dibackup.
* Dokumen harus dibackup.
* Production menggunakan HTTPS.
* Jangan menyimpan password dalam plaintext.
* Jangan menyimpan credential di Git.

---

# 31. Tahapan Pengembangan

Pengembangan harus dilakukan bertahap.

## STEP 1 — Analisis Kebutuhan

Dokumen:

```text
docs/requirements.md
docs/actors.md
docs/workflow.md
```

Tujuan:

Memastikan kebutuhan sistem sebelum coding.

---

## STEP 2 — Analisis Excel Gaji Pusat

Menunggu contoh Excel.

Analisis:

* Header.
* Kolom.
* NIP.
* Nama.
* Komponen penghasilan.
* Total.
* Format nominal.
* Format periode.
* Struktur baris.
* Kemungkinan perubahan format.

Output:

```text
docs/excel-gaji-pusat.md
```

---

## STEP 3 — Analisis Excel Potongan

Analisis semua format potongan.

Output:

```text
docs/excel-potongan.md
```

Jika format berbeda-beda, buat mekanisme mapping yang fleksibel.

---

## STEP 4 — Database

Setelah Excel dianalisis:

```text
docs/database.md
ERD
Migrations
Models
Relationships
```

---

## STEP 5 — Project Setup

Implementasi:

* Laravel.
* Docker.
* MySQL.
* Blade.
* Livewire.
* Tailwind.
* Alpine.
* Authentication.
* User.
* Role & Permission (spatie/laravel-permission).

---

## STEP 6 — Master Unit & Master Pegawai

Implementasi:

* Master Unit: CRUD, status aktif.
* Master Pegawai: CRUD, search, filter.
* Import jika diperlukan.
* Status aktif/nonaktif.

---

## STEP 7 — Periode Gaji

Implementasi:

* Create period.
* Open.
* Verify.
* Final.
* Archive.
* Lock.

---

## STEP 8 — Import Gaji Pusat

Implementasi:

```text
Upload
→ Mapping
→ Validate
→ Preview
→ Confirm
→ Import
→ Snapshot
```

---

## STEP 9 — Master Jenis Potongan

Implementasi:

* CRUD.
* Kode.
* Nama.
* Status.
* Keterangan.

---

## STEP 10 — Import & Input Potongan

Implementasi:

* Import Excel.
* Mapping.
* Validation.
* Preview.
* Manual input.
* Correction.

---

## STEP 11 — Proses Gaji

Implementasi:

```text
Data Pusat
+
Potongan
↓
Salary Record
↓
Total Penghasilan
Total Potongan
Gaji Bersih
```

---

## STEP 12 — Verifikasi & Finalisasi

Implementasi:

```text
DRAFT
↓
VERIFIKASI
↓
FINAL
```

---

## STEP 13 — Slip Gaji

Implementasi:

* PDF.
* Preview.
* Download.
* Print.
* Individual.
* Mass generation.

---

## STEP 14 — Bukti Potongan

Implementasi:

* Individual.
* Per jenis potongan.
* PDF.
* Mass generation.

---

## STEP 15 — Rekap Setoran

Implementasi:

* Rekap per jenis.
* Jumlah pegawai.
* Total.
* PDF.
* Excel.

---

## STEP 16 — Laporan

Implementasi:

* Bulanan.
* Tahunan.
* Per unit.
* Per jenis potongan.
* Penghasilan.
* Potongan.
* Netto.

---

## STEP 17 — Email

Implementasi:

* SMTP.
* Template.
* Send.
* Retry.
* Log.
* Resend.

---

## STEP 18 — Audit & Security

Implementasi:

* Audit log.
* Permission.
* Upload security.
* Data access.
* Backup.

---

## STEP 19 — Testing

Minimal:

```text
Unit Test
Feature Test
Import Test
Calculation Test
Permission Test
PDF Test
Email Test
Security Test
```

Test kasus:

```text
NIP tidak ditemukan
NIP duplikat
Excel kosong
Nominal kosong
Nominal invalid
Nominal negatif
Pegawai tidak aktif
Potongan tanpa pegawai
Import ganda
Periode sudah final
Koreksi data
```

---

## STEP 20 — Deployment

```text
Docker Production
↓
MySQL Production
↓
Nginx
↓
HTTPS
↓
SMTP
↓
Queue Worker
↓
Scheduler
↓
Backup
↓
Monitoring
```

---

# 32. Aturan untuk Claude Code

Claude Code harus mengikuti aturan berikut.

## 1. Jangan mengerjakan semua fitur sekaligus

Implementasikan berdasarkan STEP.

## 2. Baca dokumentasi sebelum coding

Minimal:

```text
README.md
docs/*
```

## 3. Jangan mengubah arsitektur tanpa alasan

Jika ada kebutuhan perubahan, dokumentasikan terlebih dahulu.

## 4. Jangan membuat asumsi format Excel

Sebelum Excel diberikan, jangan membuat mapping berdasarkan asumsi.

## 5. Jangan membuat database final terlalu cepat

Database final dibuat setelah analisis Excel.

## 6. Semua logic bisnis harus memiliki test

Terutama:

```text
Salary Calculation
Deduction
Import
Finalization
```

## 7. Setiap step harus menghasilkan kondisi aplikasi yang stabil

Jangan meninggalkan project dalam kondisi tidak dapat dijalankan.

## 8. Dokumentasi harus diperbarui

Jika struktur sistem berubah, dokumentasi harus ikut berubah.

---

# 33. Git Workflow

Gunakan commit kecil dan jelas.

Contoh:

```text
feat: add employee management
feat: add salary periods
feat: add central salary import
feat: add deduction management
feat: add salary calculation
feat: add salary finalization
feat: add payslip generation
feat: add deduction receipt
feat: add monthly reports
feat: add email notification
```

Hindari satu commit besar untuk seluruh sistem.

---

# 34. Dokumentasi Proyek

Struktur dokumentasi:

```text
README.md

docs/
├── requirements.md
├── actors.md
├── workflow.md
├── database.md
├── excel-gaji-pusat.md
├── excel-potongan.md
├── import-system.md
├── salary-calculation.md
├── finalization.md
├── payslip.md
├── deduction.md
├── reports.md
├── email.md
├── security.md
└── deployment.md
```

---

# 35. Status Proyek

Saat ini:

```text
[✓] Konsep sistem
[✓] Batasan sistem
[✓] Data pusat
[✓] Data potongan
[✓] Periode
[✓] Slip gaji
[✓] Bukti potongan
[✓] Rekap setoran
[✓] Laporan
[✓] Email
[✓] Arsitektur teknologi
[✓] Tahapan pengembangan

[ ] Excel gaji pusat
[ ] Excel potongan
[ ] Final requirements
[ ] Final database
[ ] Implementasi
```

---

# 36. Langkah Berikutnya

**Jangan mulai coding sebelum contoh Excel diberikan.**

Berikutnya:

```text
1. Upload Excel gaji pusat
2. Upload Excel potongan
3. Analisis struktur Excel
4. Tentukan mapping
5. Tentukan validasi
6. Finalisasi database
7. Mulai implementasi STEP 1
```

Setelah contoh Excel tersedia, buat dokumentasi:

```text
docs/excel-gaji-pusat.md
docs/excel-potongan.md
docs/database.md
docs/workflow.md
```

Kemudian pengembangan dilakukan secara bertahap menggunakan Claude Code.

---

# 37. Target Akhir

Target sistem:

```text
                 DATA GAJI PUSAT
                       │
                       ▼
                ┌──────────────┐
                │ IMPORT EXCEL │
                └──────┬───────┘
                       │
                       ▼
               DATA PENGHASILAN
                       │
                       │
        ┌──────────────┴──────────────┐
        │                             │
        ▼                             ▼
 DATA POTONGAN                  DATA TAMBAHAN
        │                             │
        └──────────────┬──────────────┘
                       ▼
                 PROSES GAJI
                       │
                       ▼
                VALIDASI
                       │
                       ▼
                 FINALISASI
                       │
        ┌──────────────┼──────────────┐
        │              │              │
        ▼              ▼              ▼
    SLIP GAJI     BUKTI POTONGAN   SETORAN
                                    POTONGAN
        │
        ▼
   EMAIL PEGAWAI
        │
        ▼
      LAPORAN
```

## Prinsip terakhir

Sistem harus tetap sederhana dari sisi operator, tetapi memiliki struktur data yang kuat di belakangnya.

Operator idealnya cukup melakukan:

```text
1. Pilih periode
2. Upload data pusat
3. Upload data potongan
4. Periksa hasil
5. Verifikasi
6. Finalisasi
7. Cetak/kirim dokumen
8. Ambil laporan
```

Sementara sistem menangani validasi, penggabungan data, perhitungan, histori, dokumen, audit, dan notifikasi secara otomatis.

# gaji

<!-- Ringkasan singkat: apa project ini, untuk siapa/apa tujuannya. -->

## Arsitektur

<!-- Komponen utama, stack yang dipakai, keputusan desain penting. -->

## Konvensi

<!-- Gaya kode, struktur folder, hal yang harus/tidak boleh dilakukan di project ini. -->

## Status

Lihat `PROGRESS.md` untuk progres detail dan riwayat sesi kerja.
