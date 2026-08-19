# Alur Kerja (Workflow)

Sumber: CLAUDE.md §7, §12–§22, diperkaya temuan `docs/excel-gaji-pusat.md` dan `docs/excel-potongan.md`.

## 1. Siklus Hidup Periode Gaji

```text
DRAFT ──▶ VERIFIKASI ──▶ FINAL ──▶ ARSIP
  ▲                         │
  │                         ▼
  └──────── Revisi (versi baru, kembali ke VERIFIKASI) ──┘
```

- **DRAFT** — Operator bisa import/edit data pusat & potongan bebas.
- **VERIFIKASI** — Data dikunci untuk edit oleh siapa pun selain Verifikator yang sedang memproses (locking §17). Verifikator memeriksa; jika ada masalah, periode **dikembalikan ke DRAFT** oleh Verifikator (transisi mundur, tidak dijelaskan eksplisit di CLAUDE.md tapi diperlukan secara praktis — perlu dikonfirmasi apakah ini termasuk alur resmi).
- **FINAL** — Data terkunci (append-only). Slip, bukti potongan, email, laporan bisa diproses.
- **ARSIP** — Periode selesai, jadi histori read-only.
- **Revisi** — hanya dari status FINAL: buat salinan versi baru → VERIFIKASI → diperiksa ulang → FINAL lagi. Versi lama ditandai `superseded`, tetap tersimpan (§17).

## 2. Alur Operasional End-to-End (per periode)

```text
1. Buat/Buka Periode Gaji (mis. "Agustus 2026") — status DRAFT
        │
2. Upload Excel Gaji Pusat  ──▶ Deteksi Struktur ──▶ Mapping Kolom ──▶ Validasi ──▶ Preview ──▶ Konfirmasi ──▶ Import ──▶ Snapshot
        │        (all-or-nothing: 1 baris invalid = seluruh import ditolak — §12)
        ▼
3. Upload/Input Data Potongan (bisa berkali-kali, dari berbagai jenis potongan)
        │        (all-or-nothing per periode & per jenis potongan — §13)
        │        Catatan: file potongan riil tidak selalu punya kolom NIP —
        │        lihat mekanisme mapping NPP↔NIP di docs/excel-potongan.md §5
        ▼
4. Proses Gaji — sistem menggabungkan data pusat (bersih) + data potongan
        │        → Total Penghasilan, Total Potongan, Gaji Bersih per pegawai (§15)
        │        Basis "Total Penghasilan": kolom `bersih` dari pusat —
        │        lihat docs/excel-gaji-pusat.md §4
        ▼
5. Validasi Pra-Finalisasi (§16) — semua checklist harus lolos
        ▼
6. Ajukan Verifikasi — status DRAFT → VERIFIKASI (periode terkunci — §17)
        ▼
7. Verifikator memeriksa
        ├── Jika ada masalah → kembali ke Operator (perlu diklarifikasi: kembali ke DRAFT?)
        └── Jika OK → Finalisasi — status VERIFIKASI → FINAL (atomik, tidak boleh "setengah final")
        ▼
8. Generate Dokumen
        ├── Slip Gaji (individual / massal) — §18
        └── Bukti Potongan (per jenis potongan / massal) — §19
        ▼
9. Kirim Email Notifikasi Slip ke Pegawai — status: BELUM DIKIRIM → TERKIRIM/GAGAL → (bisa DIKIRIM ULANG) — §22
        ▼
10. Rekap Setoran Potongan (per jenis potongan, untuk proses setoran manual di luar sistem) — §20
        ▼
11. Laporan Bulanan tersedia — §21
        ▼
12. (Akhir tahun) Laporan Tahunan — rekap seluruh periode — §21
        ▼
13. Periode diarsipkan — status FINAL → ARSIP
```

## 3. Alur Import Gaji Pusat (detail §12)

```text
Upload Excel
   │
   ▼
Deteksi Struktur (cek jumlah kolom, header — bandingkan dgn format dikenal sistem)
   │
   ▼
Mapping Kolom (kolom sumber → field sistem; lihat mapping awal di docs/excel-gaji-pusat.md §7)
   │
   ▼
Validasi per baris:
   • NIP tidak ditemukan / duplikat
   • Data kosong / nominal tidak valid / bukan angka
   • Komponen tidak dikenali (kdjns di luar daftar dikenal, dst.)
   • Pegawai tidak aktif
   • Periode sudah memiliki data
   • Total tidak sesuai (validasi ulang rumus §3 di docs/excel-gaji-pusat.md)
   │
   ▼
Preview — tampilkan error per baris agar operator bisa koreksi
   │
   ├── Ada baris invalid → operator perbaiki di Excel/Preview → ulangi validasi
   │        (ALL-OR-NOTHING: tidak ada baris yang masuk sebagian)
   ▼
Konfirmasi → Import → Snapshot (catat file, waktu upload, uploader, status)
```

## 4. Alur Import/Input Potongan (detail §13, disesuaikan temuan riil)

Berbeda dari asumsi awal "1 file = 1 jenis potongan", data riil fakultas (`potongan_fakultas.xlsx`) menunjukkan **1 file bisa berisi banyak kolom = banyak jenis potongan sekaligus**. Alur disesuaikan:

```text
Upload Excel
   │
   ▼
Mapping Kolom → Jenis Potongan (satu file, satu atau banyak kolom nominal
   masing-masing dipetakan ke satu Jenis Potongan dari Master Jenis Potongan §4)
   │
   ▼
Mapping Baris → Pegawai
   • Jika file punya NIP: cocokkan langsung
   • Jika file hanya punya identifier internal (mis. NPP fakultas, lihat
     docs/excel-potongan.md §5): cocokkan via tabel mapping NPP↔NIP yang
     sudah ada di Master Pegawai; baris dengan NPP tak dikenal = error,
     operator memetakan manual di layar Preview
   │
   ▼
Validasi per baris (nominal invalid, negatif, pegawai tidak ditemukan/tidak aktif, dst.)
   │
   ▼
Preview → koreksi jika ada error (ALL-OR-NOTHING per periode & per jenis potongan)
   │
   ▼
Konfirmasi → Import
```

Input manual (§14) mengikuti pola sama tanpa tahap upload: pilih pegawai, jenis potongan, periode, nominal, keterangan — langsung tercatat di audit log.

## 5. Alur Revisi Data FINAL (§17)

```text
Periode FINAL (sudah pernah kirim slip ke pegawai)
   │
   ▼
Operator/Verifikator "Ajukan Revisi" — alasan wajib diisi
   │
   ▼
Sistem membuat salinan periode sebagai versi baru (n+1)
   │
   ▼
Versi baru → status VERIFIKASI
   │
   ▼
Diperiksa & difinalisasi ulang (mengikuti alur §2 langkah 7 dst.)
   │
   ▼
Versi lama ditandai "superseded" — tetap tersimpan, tetap bisa diakses sbg arsip
   │
   ▼
Slip/bukti potongan hasil revisi diterbitkan sbg dokumen BARU dengan
penanda "Revisi", dikirim ulang via email jika perlu
   │
   ▼
Audit Log mencatat: siapa mengajukan, alasan, apa yang berubah
```

## 6. Status yang Perlu Dipantau di Dashboard (§10)

Per periode aktif: Status Periode (DRAFT/VERIFIKASI/FINAL/ARSIP), Status Import (pusat & potongan), Status Verifikasi, Status Email (BELUM DIKIRIM/TERKIRIM/GAGAL/DIKIRIM ULANG), plus agregat (jumlah pegawai, total penghasilan, total potongan, total gaji bersih).

## 7. Pertanyaan terbuka terkait alur

1. Apakah ada transisi eksplisit **VERIFIKASI → DRAFT** (dikembalikan oleh Verifikator jika data bermasalah), atau koreksi di tahap VERIFIKASI ditangani dengan cara lain?
2. Apakah "Approval Pimpinan" (disebut kondisional di §23) adalah langkah formal terpisah dalam alur finalisasi, atau tidak berlaku untuk versi awal sistem?
3. Untuk Rekap Setoran (§20) — apakah dibuat otomatis begitu FINAL, atau operator generate manual kapan saja setelah FINAL?
