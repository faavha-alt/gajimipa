# Pemetaan Field: Master Pegawai vs Komponen Periodik

Dokumen ini menjawab kebutuhan pemisahan tegas antara:

1. Field yang **melekat ke pegawai** (disimpan di Master Pegawai, tidak berulang tiap periode).
2. Field yang **komponen periodik dari pusat** — penghasilan & potongan yang sudah dipotong pusat, menghasilkan **Bersih dari Pusat**.
3. Field yang **komponen periodik dari fakultas** — potongan tambahan yang dipotong dari Bersih dari Pusat, menghasilkan **Gaji Bersih Final (Take Home)**.

Rumus yang diverifikasi:

```text
Total Penghasilan Kotor (pusat)
    − Total Potongan Pusat (PFK, PPh, BPJS, dst.)
    = Bersih dari Pusat
        − Total Potongan Fakultas (koperasi, musola, dst.)
        = Gaji Bersih Final (Take Home)
```

Diverifikasi dengan skrip terhadap **seluruh baris** kedua file contoh (bukan sampel manual) — lihat §3 dan §4.

---

## 1. Field yang Melekat ke Pegawai (→ Master Pegawai)

Field ini **tidak dijumlahkan** ke perhitungan gaji — sifatnya atribut identitas/administratif pegawai. Disimpan di tabel `employees`, di-*update* saat berubah, **tidak diduplikasi per periode** (kecuali kebutuhan histori atas perubahan itu sendiri — lihat catatan §1.1).

| Kolom sumber (pusat) | Field Master Pegawai | Catatan |
|---|---|---|
| `nip` | NIP (identifier utama, §11 CLAUDE.md) | |
| `nmpeg` | Nama | trim whitespace saat import |
| `npwp` | NPWP | data sensitif — putuskan perlu disimpan atau tidak |
| `nmrek`, `nm_bank`, `rekening`, `kdbankspan`, `nmbankspan` | Info rekening | data sensitif, di luar cakupan sistem (§6: bukan payroll banking) — kandidat kuat untuk **tidak diimpor sama sekali** |
| `kdpos`, `kdnegara`, `kdkppn`, `tipesup` | Atribut administratif satker/alamat | kegunaan untuk fakultas belum jelas, kemungkinan tidak perlu disimpan |
| `kdduduk` | Kode kedudukan pegawai | perlu tabel referensi kode dari pusat |
| `kdkawin` | Status kawin | |
| `sandi` | Kode internal pusat | kegunaan tidak jelas untuk fakultas |

Field internal fakultas (dari file potongan, §5 `docs/excel-potongan.md`):

| Kolom sumber (fakultas) | Field Master Pegawai | Catatan |
|---|---|---|
| `NPP` | Nomor internal fakultas (mapping ke NIP) | **wajib** untuk mencocokkan baris potongan yang tidak punya NIP |

### 1.1 Field "melekat" tapi sebenarnya bisa berubah antar periode

Tiga field berikut **melekat ke pegawai** secara konsep, tapi nilainya **bisa berubah dari waktu ke waktu** (kenaikan pangkat, mutasi jabatan) — bukan potongan/penghasilan, tapi juga bukan benar-benar statis selamanya:

| Kolom sumber | Field | Catatan |
|---|---|---|
| `kdgol` | Golongan/pangkat | berubah saat kenaikan pangkat |
| `kdjab` | Jabatan | berubah saat mutasi jabatan |
| `kdgapok` | Kode gaji pokok/golongan gaji | terkait `kdgol`, ikut berubah |

**Rekomendasi:** simpan sebagai field di Master Pegawai (nilai *terkini*), tapi **snapshot nilainya juga direkam di `salary_records` tiap periode** (bukan hanya referensi ke Master Pegawai) — supaya histori slip periode lama tetap menampilkan golongan/jabatan **saat itu**, bukan ikut berubah kalau pegawai naik pangkat di periode berikutnya. Ini konsisten dengan prinsip histori §29 CLAUDE.md.

---

## 2. Field Komponen Periodik dari Pusat (→ `salary_records` + `salary_components`)

Field ini **wajib berulang tiap periode** (nilainya beda tiap bulan) dan **berasal dari import Excel pusat**, bersifat read-only/tidak diedit manual (§3.1 CLAUDE.md: sistem tidak boleh mengubah nilai sumber pusat).

### 2.1 Komponen Penghasilan (kategori: `PENGHASILAN`)

| Kolom sumber | Komponen |
|---|---|
| `gjpokok` | Gaji Pokok |
| `tjistri` | Tunjangan Istri/Suami |
| `tjanak` | Tunjangan Anak |
| `tjupns` | Tunjangan Umum PNS |
| `tjstruk` | Tunjangan Struktural |
| `tjfungs` | Tunjangan Fungsional |
| `tjdaerah` | Tunjangan Daerah |
| `tjpencil` | Tunjangan Pengabdian (perlu konfirmasi nama resmi) |
| `tjlain` | Tunjangan Lainnya |
| `tjkompen` | Tunjangan Kompensasi |
| `pembul` | Pembulatan |
| `tjberas` | Tunjangan Beras |
| `tjpph` | Tunjangan PPh |

**Total Penghasilan Kotor** = jumlah 13 komponen di atas.

### 2.2 Komponen Potongan Pusat (kategori: `POTONGAN_PUSAT`)

**Temuan penting:** komponen ini **bukan bagian dari "Data Potongan" yang dikelola fakultas (§4 CLAUDE.md)**. Ini adalah potongan yang **sudah dipotong pusat sendiri**, sudah final/tidak bisa diubah fakultas, dan harus disimpan terpisah dari `deduction_records` (yang dikelola fakultas) — sebagai bagian dari `salary_components` (read-only, sumber = import pusat), dengan kategori berbeda dari komponen penghasilan.

| Kolom sumber | Komponen |
|---|---|
| `potpfkbul` | Potongan PFK Bulanan |
| `potpfk2` | Potongan PFK 2% |
| `potpfk10` | Potongan PFK 10% (iuran pensiun) |
| `potpph` | Potongan PPh |
| `potswrum` | Potongan Sewa Rumah |
| `potkelbtj` | Potongan Kelebihan Tunjangan |
| `potlain` | Potongan Lainnya (pusat) |
| `pottabrum` | Potongan Tabungan Perumahan |
| `bpjs` | Potongan BPJS |
| `bpjs2` | Potongan BPJS 2 |

**Total Potongan Pusat** = jumlah 10 komponen di atas.

### 2.3 Hasil: Bersih dari Pusat

```text
Bersih dari Pusat = Total Penghasilan Kotor − Total Potongan Pusat
```

Kolom sumber: `bersih` (AQ) — **nilai ini diimpor apa adanya** dari pusat, lalu hasil hitung ulang sistem (2.1 − 2.2) dipakai untuk **validasi** (cocok/tidak dengan nilai `bersih` yang diberikan pusat), bukan menggantikannya. Ini realisasi validasi "Total tidak sesuai" di §12 CLAUDE.md.

---

## 3. Verifikasi Total Penghasilan − Total Potongan Pusat = Bersih (seluruh baris)

Dihitung ulang oleh skrip untuk ketujuh pegawai dalam contoh — **cocok 100%**:

| Nama | Total Penghasilan Kotor | Total Potongan Pusat | Bersih (hitung ulang) | Bersih (dari file, kolom `AQ`) | Status |
|---|---:|---:|---:|---:|:---:|
| Suranto | 8.590.495 | 765.895 | 7.824.600 | 7.824.600 | ✓ OK |
| Sri Subanti | 8.633.038 | 804.438 | 7.828.600 | 7.828.600 | ✓ OK |
| Diari Indriati | 7.650.070 | 709.270 | 6.940.800 | 6.940.800 | ✓ OK |
| Ari Handono Ramelan | 8.792.391 | 778.091 | 8.014.300 | 8.014.300 | ✓ OK |
| Cari | 7.912.624 | 743.424 | 7.169.200 | 7.169.200 | ✓ OK |
| Darmanto | 6.736.525 | 566.925 | 6.169.600 | 6.169.600 | ✓ OK |
| Suharyana | 6.609.138 | 606.238 | 6.002.900 | 6.002.900 | ✓ OK |

**Kesimpulan:** rumus `Total Penghasilan Kotor − Total Potongan Pusat = Bersih` valid untuk 100% data contoh (7/7 baris), aman dijadikan aturan validasi sistem.

---

## 4. Field Komponen Periodik dari Fakultas (→ `deduction_records`, kategori `POTONGAN_FAKULTAS`)

Ini adalah "Data Potongan" sesungguhnya yang dimaksud §4 CLAUDE.md — **dikelola fakultas**, bisa diimpor/diinput manual, bisa dikoreksi selama periode masih DRAFT.

| Kolom sumber | Jenis Potongan (nama sementara) |
|---|---|
| `D` (S.Wajib) | KOPERASI_SIMPANAN_WAJIB |
| `E` (Angsuran) | KOPERASI_ANGSURAN |
| `F` (Sikopin) | KOPERASI_SIKOPIN |
| `G` (BNI/BPD) | KENDARAAN_BNI_BPD |
| `H` (Gota) | IURAN_GOTA *(perlu konfirmasi nama resmi)* |
| `I` (Kesejahteraan) | IURAN_KESEJAHTERAAN |
| `J` (Masjid dan Zakat) | MUSOLA_MASJID_ZAKAT |
| `K` (DW) | IURAN_DHARMA_WANITA |
| `L` (FMIPA) | SIMPANAN_WAJIB_FMIPA |
| `M` (FMIPA) | PRALENAN_FMIPA *(perlu konfirmasi nama resmi)* |
| `N` (Pagyban) | IURAN_WAJIB_PAGUYUBAN |
| `O` (Paguyuban) | ANGSURAN_PAGUYUBAN |
| `P` (UM) | POTONGAN_UM |
| `Q` (Jurusan) | POTONGAN_JURUSAN |
| `R` (Rumah) | POTONGAN_RUMAH |
| `S` (Masjid MIPA) | MASJID_MIPA |
| `T` (Biologi Mhs) | BIOLOGI_MHS *(perlu konfirmasi nama resmi)* |

**Total Potongan Fakultas** = jumlah 17 komponen di atas (kolom `U` di file sumber = total ini, sudah divalidasi cocok).

### 4.1 Hasil akhir: Gaji Bersih Final

```text
Gaji Bersih Final = Bersih dari Pusat − Total Potongan Fakultas
```

Kolom sumber: `W` ("Sisa Gaji/Rek. BNI") di file potongan fakultas.

---

## 5. Verifikasi Bersih dari Pusat − Total Potongan Fakultas = Gaji Bersih Final

Dihitung ulang untuk seluruh 8 baris di file potongan fakultas (1 baris pensiun tanpa data numerik dikecualikan) — **cocok 100%** untuk `U` (total potongan fakultas) dan `W` (gaji bersih final):

| Nama (fakultas) | Bersih dari Pusat (`V`) | Total Potongan Fakultas (`U`) | Gaji Bersih Final (hitung ulang) | Gaji Bersih Final (file, `W`) | Status | Cocok dgn `bersih` pusat? |
|---|---:|---:|---:|---:|:---:|:---:|
| Sutarno | 8.014.300 | 437.000 | 7.577.300 | 7.577.300 | ✓ OK | tidak ada di sampel pusat |
| Sri Subanti | 7.828.600 | 3.422.000 | 4.406.600 | 4.406.600 | ✓ OK | ✓ = 7.828.600 |
| Okid Parama Astirin | 7.824.600 | 347.000 | 7.477.600 | 7.477.600 | ✓ OK | tidak ada di sampel pusat |
| Sentot Budi Rahardjo | — | — | — | — | — | **pensiun Juni 2026, data kosong** |
| Diari Indriati | 6.940.800 | 822.000 | 6.118.800 | 6.118.800 | ✓ OK | ✓ = 6.940.800 |
| Bambang Harjito | 7.565.700 | 237.000 | 7.328.700 | 7.328.700 | ✓ OK | tidak ada di sampel pusat |
| Suranto | 7.824.600 | 272.000 | 7.552.600 | 7.552.600 | ✓ OK | ✓ = 7.824.600 |
| Ari Handono Ramelan | 8.014.300 | 1.422.000 | 6.592.300 | 6.592.300 | ✓ OK | ✓ = 8.014.300 |

**Catatan:** hanya 4 dari 8 nama di file fakultas yang kebetulan ada juga di 7 nama sampel file pusat (sampel pusat memang cuma 7 orang, bukan daftar lengkap). Untuk keempat nama yang overlap, kolom `V` ("Gaji Kotor" versi fakultas) **cocok persis 4/4** dengan kolom `bersih` (`AQ`) di file pusat — mengonfirmasi ulang temuan di `docs/excel-gaji-pusat.md` §4: **`V` bukan gaji kotor, melainkan Bersih dari Pusat.**

**Kesimpulan:** rumus `Bersih dari Pusat − Total Potongan Fakultas = Gaji Bersih Final` valid untuk 100% baris yang datanya lengkap (7/7, di luar 1 baris pensiun yang memang tidak punya data potongan/gaji).

---

## 6. Ringkasan Struktur Data (mengoreksi §28 CLAUDE.md)

Spesifikasi awal §28 CLAUDE.md hanya menyebut `salary_components` (penghasilan) dan `deduction_records` (potongan fakultas), **tanpa tempat eksplisit untuk potongan yang sudah dipotong pusat** (PFK/PPh/BPJS). Berdasarkan temuan di atas, struktur perlu tiga kategori komponen, bukan dua:

```text
employees (Master Pegawai)
   │  NIP, Nama, NPP-fakultas, golongan/jabatan terkini, dst. (§1)
   ▼
salary_periods
   ▼
salary_records  (1 per pegawai per periode)
   │
   ├── salary_components  kategori PENGHASILAN     (§2.1 — dari import pusat, read-only)
   ├── salary_components  kategori POTONGAN_PUSAT   (§2.2 — dari import pusat, read-only)
   │        └─▶ bersih_pusat  (kolom tersimpan langsung + tervalidasi silang, §2.3)
   │
   └── deduction_records  kategori POTONGAN_FAKULTAS (§4 — dikelola fakultas, editable saat DRAFT)
            └─▶ gaji_bersih_final = bersih_pusat − total_potongan_fakultas (§4.1)
```

**Ini perlu dikonfirmasi ke pihak fakultas** sebagai penyesuaian resmi terhadap §28 sebelum STEP 4 (finalisasi database) dimulai, karena mengubah jumlah tabel/kategori dari desain awal.

## 7. Pertanyaan tambahan dari analisis ini

1. Apakah field golongan/jabatan/kode gaji pokok pegawai (`kdgol`, `kdjab`, `kdgapok`) perlu di-snapshot per periode di `salary_records` (§1.1), atau cukup disimpan sekali di Master Pegawai dan dianggap "selalu nilai terkini" di semua slip?
2. Apakah field rekening/NPWP/nama pemilik rekening (§1) memang perlu disimpan sama sekali, mengingat sistem eksplisit bukan payroll banking?
3. Konfirmasi nama resmi untuk 3 jenis potongan fakultas yang header-nya terpotong: `Gota`, `Pralenan FMIPA`, `Biologi Mhs` (§4).
