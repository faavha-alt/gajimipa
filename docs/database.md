# Database — ERD & Spesifikasi Tabel

STEP 4 CLAUDE.md. Skema ini dibangun berdasarkan §28/§29 CLAUDE.md, dikoreksi oleh temuan `docs/pemetaan-field-gaji.md` §6 (3 kategori komponen, bukan 2) dan `docs/keputusan-desain.md` §F (kolom tambahan, master data baru, tabel mapping).

Seluruh kolom nominal: `decimal(15,2)` (§29 CLAUDE.md — `float`/`double` dilarang).
Seluruh timestamp: disimpan UTC oleh Laravel seperti biasa, ditampilkan dalam WIB oleh aplikasi (`config/app.php` timezone = Asia/Jakarta, sudah di-set STEP 5).

## 1. Diagram Relasi (ringkas)

```text
units ──┐
        ├──< employees >──┐
employee_statuses ──┘     │
                           ├──< salary_records >──< salary_components
users (login, opsional employee_id) │         │
                                     │         ├──< deduction_records >── deduction_types
income_types ──────────────────────┘         │
                                               ├──< payslips
salary_periods ──< salary_imports ──< salary_import_rows          │
       │                                                            └──< email_logs
       ├──< salary_records
       ├──< deduction_imports
       └──< submission_records ── deduction_types

deduction_records ──< deduction_receipts

import_column_mappings (dipakai salary_imports & deduction_imports)
audit_logs (mencatat aktivitas lintas tabel, tidak FK ketat)
system_settings (key-value, berdiri sendiri)
```

## 2. Master Data

### `units` (§11.1)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| kode_unit | string(20) unique | |
| nama_unit | string(150) | |
| status_aktif | boolean default true | |
| timestamps | | |

### `employee_statuses` (Master Status Pegawai, §11)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| kode | string(20) unique | mis. PNS, PPPK, NON_PNS, DOSEN, TENDIK |
| nama | string(100) | |
| status_aktif | boolean default true | |
| timestamps | | |

### `deduction_types` (Master Jenis Potongan, §4)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| kode | string(50) unique | mis. `KOPERASI_SIMPANAN_WAJIB` |
| nama | string(150) | label tampilan — verbatim header Excel untuk jenis yang belum jelas artinya (keputusan C2) |
| keterangan | text nullable | |
| status_aktif | boolean default true | |
| timestamps | | |

### `income_types` (Master Jenis Gaji Pusat, keputusan A2 — untuk kolom `kdjns`)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| kode | string(10) unique | nilai `kdjns` mentah, mis. `1` |
| nama | string(100) | mis. "Gaji Induk" |
| status_aktif | boolean default true | |
| timestamps | | |

### `import_column_mappings` (keputusan A1/C6 — mapping kolom sebagai data, bukan hardcode)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nama_template | string(150) | mis. "Gaji Pusat — Format Standar", "Potongan — Rekap Fakultas" |
| jenis | enum(`GAJI_PUSAT`,`POTONGAN_FAKULTAS`) | |
| definisi_kolom | json | daftar kolom sumber (huruf/nama header) → field sistem / `deduction_type_id` |
| status_aktif | boolean default true | |
| timestamps | | |

### `employees` (Master Pegawai, §11)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nip | string(20) unique | identifier utama (§11) |
| nik | string(16) unique nullable | Nomor Induk Kependudukan — ditambahkan 2026-08-19, lihat keputusan A3 (revisi) |
| nama | string(150) | |
| unit_id | FK → units, nullable | |
| employee_status_id | FK → employee_statuses, nullable | |
| email | string(150) unique nullable | dipakai notifikasi (§22) & login (keputusan D2) |
| no_hp | string(20) nullable | ditambahkan 2026-08-19 |
| kode_npp_fakultas | string(20) unique nullable | mapping ke NPP fakultas (keputusan C1) |
| id_simpeg | string(30) unique nullable | ID SIMPEG — ditambahkan 2026-08-19 |
| npwp | string(25) unique nullable | **data sensitif** — ditambahkan 2026-08-19, lihat keputusan A3 (revisi) |
| no_rekening | string(30) nullable | **data sensitif** — ditambahkan 2026-08-19, lihat keputusan A3 (revisi) |
| golongan_saat_ini | string(10) nullable | nilai terkini (`kdgol`) — histori per periode ada di `salary_records` (keputusan B1) |
| jabatan_saat_ini | string(10) nullable | (`kdjab`) |
| kode_gaji_pokok_saat_ini | string(10) nullable | (`kdgapok`) |
| status_kawin_saat_ini | string(10) nullable | (`kdkawin`) |
| status_aktif | boolean default true | |
| timestamps | | |

`npwp` dan `no_rekening`: `$hidden` di model (tidak muncul di serialisasi array/JSON default), dan sengaja tidak pernah di-`select()` di query daftar pegawai — hanya terlihat/diedit di form Master Pegawai untuk role dengan permission `employees.manage` (Operator & Super Admin). Lihat keputusan A3 (revisi) di `docs/keputusan-desain.md`.

## 3. Auth

### `users` (bawaan Laravel, sudah ada sejak STEP 5) — ditambah:
| Kolom baru | Tipe | Keterangan |
|---|---|---|
| employee_id | FK → employees, nullable | diisi kalau user ini adalah Pegawai; null untuk Super Admin/Operator/Verifikator/Pimpinan yang bukan pegawai |

Role & permission: `roles`, `permissions`, `model_has_roles`, dst. dikelola `spatie/laravel-permission` (sudah ter-migrate sejak STEP 5). Role di-seed: `super_admin`, `operator_gaji`, `verifikator`, `pimpinan`, `pegawai` (lihat `docs/actors.md`).

## 4. Periode & Import

### `salary_periods` (§7)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nama_periode | string(30) | mis. "Agustus 2026" |
| bulan | tinyint | 1–12 |
| tahun | smallint | |
| status | string(20) | `DRAFT`,`VERIFIKASI`,`FINAL`,`ARSIP` — string terkelola, bukan enum DB kaku (keputusan D1, gampang disisipi status baru) |
| versi | smallint default 1 | untuk mekanisme revisi §17 |
| periode_asal_id | FK → salary_periods, nullable, self | diisi kalau baris ini hasil revisi dari periode lain |
| status_supersede | boolean default false | true kalau versi ini sudah digantikan revisi baru |
| locked_by_user_id | FK → users, nullable | locking saat VERIFIKASI/finalisasi (§17) |
| locked_at | timestamp nullable | |
| alasan_revisi | text nullable | diisi kalau `periode_asal_id` terisi |
| unique(bulan, tahun, versi) | | |
| timestamps | | |

### `salary_imports` (§8, snapshot import gaji pusat)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| salary_period_id | FK → salary_periods | |
| import_column_mapping_id | FK → import_column_mappings, nullable | |
| nama_file | string(255) | |
| path_file | string(255) | disimpan di storage, bukan public |
| diupload_oleh | FK → users | |
| status | string(20) | `DRAFT_PREVIEW`,`TERVERIFIKASI`,`DIBATALKAN` |
| jumlah_baris | int | |
| jumlah_error | int default 0 | |
| timestamps | | |

### `salary_import_rows`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| salary_import_id | FK → salary_imports | |
| nomor_baris | int | baris ke berapa di Excel |
| data_mentah | json | isi baris asli (untuk audit/debug) |
| employee_id | FK → employees, nullable | null kalau NIP tidak ditemukan (baris error) |
| status | string(20) | `VALID`,`ERROR` |
| pesan_error | text nullable | |
| timestamps | | |

### `deduction_imports` (§13, snapshot import potongan — sejenis `salary_imports`)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| salary_period_id | FK → salary_periods | |
| import_column_mapping_id | FK → import_column_mappings, nullable | |
| nama_file | string(255) | |
| path_file | string(255) | |
| diupload_oleh | FK → users | |
| status | string(20) | |
| jumlah_baris | int | |
| jumlah_error | int default 0 | |
| timestamps | | |

## 5. Data Gaji per Periode

### `salary_records` (§15 — hasil gabungan data pusat, 1 baris per pegawai per periode)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| salary_period_id | FK → salary_periods | |
| employee_id | FK → employees | |
| salary_import_id | FK → salary_imports, nullable | sumber data pusat |
| income_type_id | FK → income_types, nullable | dari `kdjns` |
| nip_snapshot | string(20) | salinan NIP saat itu |
| nama_snapshot | string(150) | |
| unit_snapshot | string(150) nullable | |
| golongan_snapshot | string(10) nullable | (keputusan B1) |
| jabatan_snapshot | string(10) nullable | |
| kode_gaji_pokok_snapshot | string(10) nullable | |
| status_kawin_snapshot | string(10) nullable | |
| total_penghasilan_kotor | decimal(15,2) | jumlah komponen kategori PENGHASILAN |
| total_potongan_pusat | decimal(15,2) | jumlah komponen kategori POTONGAN_PUSAT |
| bersih_pusat | decimal(15,2) | = kotor − potongan pusat; divalidasi vs nilai asli dari file (`docs/pemetaan-field-gaji.md` §3) |
| total_potongan_fakultas | decimal(15,2) default 0 | jumlah `deduction_records` terkait, dihitung ulang saat proses gaji (§15) |
| gaji_bersih_final | decimal(15,2) default 0 | = bersih_pusat − total_potongan_fakultas (keputusan C7: selalu dihitung sistem) |
| unique(salary_period_id, employee_id) | | |
| timestamps | | |

### `salary_components` (koreksi §28 — gabung kategori PENGHASILAN & POTONGAN_PUSAT, lihat `pemetaan-field-gaji.md` §6)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| salary_record_id | FK → salary_records | |
| kategori | string(20) | `PENGHASILAN` atau `POTONGAN_PUSAT` |
| kode_komponen | string(30) | mis. `gjpokok`, `tjistri`, `potpfk10`, `bpjs` — kode kolom sumber asli |
| nama_komponen | string(100) | label tampilan (mis. "Gaji Pokok") |
| nominal | decimal(15,2) | |
| timestamps | | |

### `deduction_records` (§14 — potongan fakultas, POTONGAN_FAKULTAS, bisa diedit selama DRAFT)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| salary_record_id | FK → salary_records | |
| deduction_type_id | FK → deduction_types | |
| deduction_import_id | FK → deduction_imports, nullable | null kalau input manual |
| nominal | decimal(15,2) | |
| keterangan | string(255) nullable | |
| sumber | string(10) | `IMPORT` atau `MANUAL` |
| dibuat_oleh | FK → users | |
| timestamps | | |

## 6. Dokumen

### `payslips` (§18)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| salary_record_id | FK → salary_records | |
| nomor_dokumen | string(50) unique | format berurutan per periode (§19) |
| path_file | string(255) | PDF di storage |
| is_revisi | boolean default false | |
| dibuat_oleh | FK → users | |
| timestamps | | |

### `deduction_receipts` (§19 — bukti potongan, per `deduction_record`)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| deduction_record_id | FK → deduction_records | |
| nomor_dokumen | string(50) unique | |
| path_file | string(255) | |
| is_revisi | boolean default false | |
| dibuat_oleh | FK → users | |
| timestamps | | |

### `submission_records` (§20 — rekap setoran, generate manual per keputusan E3)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| salary_period_id | FK → salary_periods | |
| deduction_type_id | FK → deduction_types | |
| jumlah_pegawai | int | |
| total_nominal | decimal(15,2) | |
| dibuat_oleh | FK → users | |
| timestamps | | |

### `email_logs` (§22)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| payslip_id | FK → payslips | |
| email_tujuan | string(150) | |
| status | string(20) | `BELUM_DIKIRIM`,`TERKIRIM`,`GAGAL`,`DIKIRIM_ULANG` |
| pesan_error | text nullable | |
| dikirim_pada | timestamp nullable | |
| timestamps | | |

## 7. Audit & Pengaturan

### `audit_logs` (§24)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users, nullable | null utk aktivitas sistem otomatis |
| aktivitas | string(50) | `LOGIN`,`IMPORT`,`EDIT`,`HAPUS`,`VERIFIKASI`,`FINALISASI`,`REVISI`,`GENERATE_DOKUMEN`,`EMAIL` |
| deskripsi | text | rincian human-readable |
| data_terkait | json nullable | payload/konteks tambahan |
| ip_address | string(45) nullable | |
| created_at | timestamp | append-only, tanpa `updated_at` |

### `system_settings` (§28)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| key | string(100) unique | |
| value | text nullable | |
| timestamps | | |

## 8. Catatan implementasi

- Semua FK memakai `restrict`/`cascade` yang dipilih konservatif: data periode FINAL tidak boleh terhapus ikut-ikutan kalau ada penghapusan di tabel induk secara tidak sengaja — default `restrictOnDelete()` kecuali disebutkan lain (mis. `salary_components`/`deduction_records` `cascadeOnDelete()` mengikuti `salary_records` induknya, karena itu bagian tak terpisahkan dari satu record).
- `audit_logs` sengaja tidak strict-FK ke semua tabel yang mungkin dicatat (pola umum audit log: longgar, tidak boleh gagal insert gara-gara constraint).
- Migrations dibuat berurutan sesuai dependency di atas (lihat daftar file di `database/migrations/`).
