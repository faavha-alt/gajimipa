# Analisis Excel Potongan Fakultas

Status: **Draft awal berdasarkan 1 contoh file.** Perlu dikonfirmasi ke pihak fakultas sebelum dijadikan dasar mapping final (lih. bagian "Pertanyaan yang perlu dikonfirmasi").

> **Update 2026-08-20**: temuan §5 di bawah ("tidak ada kolom NIP, cuma NPP") ternyata **tidak berlaku untuk file yang sebenarnya dipakai fakultas** — dikonfirmasi user file aslinya memang punya NIP langsung. Mekanisme mapping NPP↔NIP yang direkomendasikan di §5 **tidak pernah diimplementasikan berjalan** (kolom `kode_npp_fakultas` sempat dibuat di skema tapi 0% terpakai) dan sekarang **dihapus total** dari sistem (migration `2026_08_20_145744`) — `DeductionImportService` pakai NIP langsung, sama seperti Import Gaji Pusat. Sisa §5 di bawah dibiarkan sebagai arsip analisis file contoh awal, bukan deskripsi sistem saat ini.

## 1. Sumber

- File contoh: `data_gaji/potongan_fakultas.xlsx` (tidak di-commit ke git — lihat `.gitignore`).
- 1 sheet: `Sheet1`.
- **Header 2 baris** (baris 1 = kategori/merged, baris 2 = sub-label), data mulai baris 3.
- 8 baris pegawai, tampak khusus untuk **pimpinan/dosen senior FMIPA** (semua bergelar Prof./Dr.) — kemungkinan ini rekap potongan level jurusan/dekanat, bukan seluruh pegawai fakultas. **Perlu dikonfirmasi** apakah ini representatif untuk seluruh potongan pegawai atau hanya contoh sebagian.
- Merged cells di header: `D1:F1` = "Koperasi UNS" (menaungi 3 sub-kolom: S.Wajib, Angsuran, Sikopin), `P1:Q1` = "Potongan" (menaungi UM & Jurusan).

## 2. Struktur Kolom

Format file ini **sangat berbeda** dari file pusat: bukan export sistem, tapi rekapitulasi manual/spreadsheet kerja fakultas dengan header 2 baris dan penamaan kolom tidak konsisten (beberapa header kosong di baris 1, isi ada di baris 2).

| Kolom | Header baris 1 | Header baris 2 | Arti | Tipe | Contoh |
|---|---|---|---|---|---|
| A | NO | (a) | Nomor urut / kode unit-urut | string | `1/IV`, `2/IV` — format `urutan/romawi`, maknanya belum jelas |
| B | NAMA | | Nama pegawai | string | `Prof. Drs. [NAMA], MSc, PhD` |
| C | N P P | | Nomor Pokok Pegawai **internal fakultas** (bukan NIP) | string, 3 digit | `001`, `002` |
| D | Koperasi UNS | S.Wajib | Simpanan wajib koperasi | integer | `85000` |
| E | Koperasi UNS | Angsuran | Angsuran pinjaman koperasi | integer | `0` |
| F | Koperasi UNS | Sikopin | Pinjaman Sikopin (simpan pinjam) | integer | `0`–`3.000.000` |
| G | Kendaraan | BNI/BPD | Angsuran kendaraan via bank | integer | `0` |
| H | Gota | | Tidak jelas (header terpotong — mungkin "Gotong Royong"?) | integer | `3000` |
| I | Iuran Kese- | jahteraan | Iuran kesejahteraan (header terpecah 2 baris jadi 1 kata) | integer | `9000` |
| J | Pemb.Musola FMIPA | Masjid dan Zakat | Sumbangan musola/masjid & zakat | integer | `0`–`165000` |
| K | Iuran | DW | Iuran Dharma Wanita | integer | `10000` |
| L | Simp.Wajib | FMIPA | Simpanan wajib (koperasi?) tingkat FMIPA | integer | `0`/`10000` |
| M | Pralenan | FMIPA | Iuran "pralenan" FMIPA (istilah lokal, belum jelas) | integer | `5000` |
| N | Iuran wjb | Pagyban | Iuran wajib paguyuban | integer | `0` |
| O | Pot.Angsuran | Paguyuban | Angsuran paguyuban | integer | `0` |
| P | Potongan | UM | Potongan uang muka | integer | `0`–`200000` |
| Q | Potongan | Jurusan | Potongan tingkat jurusan | integer | `0`–`300000` |
| R | | Rumah | Potongan rumah dinas(?) | integer | selalu `0` di contoh |
| S | | Masjid MIPA | Sumbangan masjid MIPA (beda dari kolom J?) | integer | selalu `0` di contoh |
| T | | Biologi Mhs | Tidak jelas — potongan terkait "Biologi Mahasiswa"? | integer | selalu `0` di contoh |
| **U** | | **Jumlah Potongan** | **Total seluruh potongan fakultas (sum D..T)** | integer | `437000` |
| **V** | | **Gaji Kotor** | Angka dasar sebelum potongan fakultas | integer | `8014300` |
| **W** | | **Sisa Gaji / Rek. BNI** | `V − U`, yang ditransfer ke rekening | integer | `7577300` |
| X | | Penerimaan Tunai | Catatan tambahan (bukan nominal) | string | `"PENSIUN DI BULAN JUNI 2026"` pada baris pensiun |

## 3. Verifikasi rumus

Untuk semua baris berisi angka lengkap, berlaku:

```text
U (Jumlah Potongan) = D+E+F+G+H+I+J+K+L+M+N+O+P+Q+R+S+T
W (Sisa Gaji)        = V (Gaji Kotor) − U (Jumlah Potongan)
```

Contoh baris 3 (Sutarno): `D..T` = 85000+0+0+0+3000+9000+165000+10000+10000+5000+0+0+100000+50000+0+0+0 = 437.000 = U ✓.
`W = 8.014.300 − 437.000 = 7.577.300` ✓.

## 4. Temuan kritis: kolom "Gaji Kotor" (V) sebenarnya = "bersih" dari pusat

Mencocokkan kolom `V` di sini dengan kolom `AQ` (`bersih`) pada `dasar_acuan_univ.xlsx` untuk nama pegawai yang sama:

| Nama | V (potongan_fakultas) | AQ (dasar_acuan_univ) | Cocok? |
|---|---|---|---|
| Sri Subanti | 7.828.600 | 7.828.600 | ✓ |
| Diari Indriati | 6.940.800 | 6.940.800 | ✓ |
| Suranto | 7.824.600 | 7.824.600 | ✓ |
| Ari Handono Ramelan | 8.014.300 | 8.014.300 | ✓ |

Jadi walau labelnya "Gaji Kotor", nilainya **identik dengan `bersih` (net setelah potongan pusat)** di file pusat — lihat pembahasan lengkap di `docs/excel-gaji-pusat.md` §4. Ini mengonfirmasi bahwa alur perhitungan §5 CLAUDE.md harus memakai `bersih` (AQ) dari pusat sebagai "Total Penghasilan" input ke tahap potongan fakultas, bukan penjumlahan komponen mentah.

## 5. Temuan kritis: TIDAK ADA KOLOM NIP

File ini **hanya punya kolom NAMA dan NPP (nomor internal fakultas)** — tidak ada NIP sama sekali. Ini bertentangan langsung dengan §11 CLAUDE.md:

> "Nama tidak boleh digunakan sebagai identifier utama karena nama dapat sama."

Karena sumber Excel potongan yang diberikan fakultas memang tidak menyertakan NIP, sistem **tidak bisa mengandalkan file ini apa adanya** untuk mapping otomatis by-NIP. Opsi yang perlu didiskusikan dengan fakultas (bukan diputuskan sepihak oleh sistem):

1. **Tabel mapping NPP ↔ NIP** disimpan di Master Pegawai (`employees.kode_npp_fakultas` atau tabel mapping terpisah) — dibuat/dipelihara sekali oleh operator, lalu import potongan mencocokkan by-NPP alih-alih by-Nama.
2. Saat NPP belum dikenal sistem (pegawai baru), import ditolak per baris (§12/§13 — validasi "NIP tidak ditemukan" analognya "NPP tidak ditemukan/tidak termapping") dan operator diminta memetakan manual di layar Preview sebelum konfirmasi.
3. Nama tetap ditampilkan di layar Preview sebagai bantuan visual verifikasi bagi operator, **tapi bukan dasar pencocokan otomatis** — mencegah salah pasang data akibat nama kembar/beda ejaan gelar.

**Ini perlu dikonfirmasi ke fakultas**: apakah NPP ("001", "002", dst.) adalah nomor yang stabil/tidak berubah antar periode, sehingga layak dijadikan kunci mapping permanen di Master Pegawai.

## 6. Struktur "jenis potongan" yang dinamis (§4 CLAUDE.md)

File ini punya **15 jenis potongan berbeda** dalam 1 file (kolom D–T), bukan 1 file per jenis potongan seperti diasumsikan alur §13 ("Pilih Jenis Potongan" sebelum upload). Ini penting untuk desain mapping import:

- Kemungkinan besar mapping kolom→jenis potongan untuk sumber "rekap fakultas" semacam ini adalah **many-to-one dalam satu file** (1 file, banyak kolom, tiap kolom = 1 jenis potongan), bukan 1 file = 1 jenis potongan.
- §13 CLAUDE.md perlu penyesuaian mekanisme: mapping kolom Excel bisa memetakan **banyak kolom sekaligus ke banyak jenis potongan berbeda** dalam satu proses import, bukan cuma 1 kolom nominal ke 1 jenis potongan yang dipilih di awal. Master Jenis Potongan (§4) minimal perlu memuat 15 jenis berikut (nama sementara, tunggu konfirmasi):

```text
KOPERASI_SIMPANAN_WAJIB
KOPERASI_ANGSURAN
KOPERASI_SIKOPIN
KENDARAAN_BNI_BPD
IURAN_GOTA           (?)
IURAN_KESEJAHTERAAN
MUSOLA_MASJID_ZAKAT
IURAN_DHARMA_WANITA
SIMPANAN_WAJIB_FMIPA
PRALENAN_FMIPA        (?)
IURAN_WAJIB_PAGUYUBAN
ANGSURAN_PAGUYUBAN
POTONGAN_UM
POTONGAN_JURUSAN
POTONGAN_RUMAH
MASJID_MIPA
BIOLOGI_MHS           (?)
```

(Kolom bertanda `(?)` header aslinya terpotong/ambigu — lihat §7 pertanyaan konfirmasi.)

## 7. Kasus tepi ditemukan di contoh

- **Baris 6 (Sentot Budi Rahardjo, NPP 005)**: hanya NO, NAMA, NPP terisi, kolom potongan & gaji semuanya kosong, dengan catatan di kolom X: `"PENSIUN DI BULAN JUNI 2026"`. Ini kasus **pegawai pensiun di tengah periode** — sistem perlu menentukan bagaimana baris seperti ini diperlakukan saat validasi import (§12: "Pegawai tidak aktif" — apakah baris ini di-skip otomatis, atau tetap error dan perlu ditandai manual oleh operator, mengingat all-or-nothing per §13). **Perlu dikonfirmasi ke fakultas.**
- Beberapa kolom (`Q`, `R`, `S`, `T`) sering kosong (bukan `0`) — perlu diperjelas apakah **cell kosong = 0** atau punya makna beda (mis. "tidak berlaku" vs "berlaku tapi nol").

## 8. Kualitas data / hal yang perlu ditangani saat import

1. **Header 2 baris + merged cells** — parser import harus menangani struktur ini (mis. baris 1 dan 2 digabung jadi 1 label kolom, atau baris 1 diabaikan dan hanya baris 2 dipakai untuk kolom yang jelas, dengan fallback manual mapping untuk kolom yang label baris-2-nya kosong seperti R, S, T).
2. Format nominal di beberapa kolom pakai *number format* Excel accounting (`_-* #,##0_-;...`) — nilai sebenarnya tetap angka murni, hanya tampilan. Tidak masalah untuk import asal dibaca via nilai (`data_only`), bukan teks tampilan.
3. Tidak ada kolom Periode eksplisit dalam file (periode ditentukan dari konteks upload — sejalan dengan alur §13 "Pilih Jenis Potongan" + periode aktif yang sedang dikerjakan operator, bukan dari isi file).
4. Kolom `NO` (`"1/IV"`, dst.) formatnya tidak jelas kegunaannya untuk sistem — kemungkinan cuma nomor urut manual di spreadsheet asal, **kemungkinan tidak perlu diimpor**.

## 9. Pertanyaan yang perlu dikonfirmasi ke pihak fakultas

1. Apakah **NPP** adalah identifier yang stabil dan bisa dijadikan kunci mapping permanen ke NIP di Master Pegawai (lih. §5)?
2. Apa arti pasti kolom `Gota`, `Pralenan`, dan `Biologi Mhs` (header terpotong/istilah lokal)?
3. Apakah cell kosong pada kolom potongan = `Rp 0`, atau punya arti lain?
4. Bagaimana pegawai yang pensiun di tengah periode (contoh baris 6) seharusnya diproses — di-skip, atau tetap wajib diisi datanya sampai bulan pensiun?
5. Apakah rekap ini **mewakili seluruh pegawai** yang harus diproses tiap periode, atau hanya contoh sebagian (mengingat semua nama di contoh adalah profesor/dosen senior)?
6. Apakah format 15-kolom-potongan-dalam-1-file ini **konsisten setiap bulan**, atau daftar jenis potongan bisa bertambah/berkurang kolom dari bulan ke bulan (relevan untuk desain mapping dinamis §4/§13)?
7. Kolom `W` ("Sisa Gaji/Rek. BNI") — apakah ini murni informasi turunan (dihitung sistem), atau ada kasus di mana fakultas memasukkan nilai override manual yang tidak sama dengan `V − U`?
