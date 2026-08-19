# Analisis Excel Gaji Pusat

Status: **Draft awal berdasarkan 1 contoh file.** Perlu dikonfirmasi ke pihak fakultas/pusat sebelum dijadikan dasar migrasi/mapping final (lih. bagian "Pertanyaan yang perlu dikonfirmasi").

## 1. Sumber

- File contoh: `data_gaji/dasar_acuan_univ.xlsx` (tidak di-commit ke git — lihat `.gitignore`, berisi data pribadi & keuangan pegawai asli).
- 1 sheet: `Sheet 1`.
- Header hanya **1 baris** (baris 1), data mulai baris 2.
- Contoh berisi 7 baris pegawai, seluruhnya untuk periode **Juni 2026** (`bulan=06`, `tahun=2026`) dan satker yang sama (`kdsatker=693437`, unit `kdanak=07`).
- Ini tampak seperti potongan/sample dari export "ADK Gaji" aplikasi GPP (Gaji Pokok Pegawai) yang umum dipakai satker pemerintah — format kolom singkatan huruf kecil (kdsatker, gjpokok, dst.) khas export sistem tersebut, bukan format yang didesain untuk fakultas. **Perlu dikonfirmasi**: apakah file produksi nanti selalu punya struktur kolom persis sama, atau bisa berbeda per bulan/per jenis gaji (`kdjns`).

## 2. Struktur Kolom

50 kolom (A–AX), header baris 1 sama persis dengan nama field berikut. Semua nilai bertipe teks (`General`) di Excel — termasuk kolom numerik, yang tersimpan sebagai angka murni (bukan `text` berformat), tanpa titik/koma pemisah.

| Kolom | Nama Header | Arti (dugaan) | Tipe | Contoh (dimask) | Catatan |
|---|---|---|---|---|---|
| A | kdsatker | Kode satuan kerja | string, 6 digit | `693437` | Konstan di seluruh baris contoh |
| B | kdanak | Kode anak satker / unit | string, 2 digit | `07` | Konstan di contoh — kemungkinan berbeda per fakultas/unit di data riil |
| C | kdsubanak | Kode sub anak satker | string | `"  "` (2 spasi/kosong) | Selalu kosong di contoh |
| D | bulan | Bulan periode | string, 2 digit | `06` | |
| E | tahun | Tahun periode | string, 4 digit | `2026` | |
| F | nogaji | Nomor referensi gaji | string, 6 digit | `101801` | Konstan di contoh — perlu dikonfirmasi maknanya |
| G | kdjns | Kode jenis gaji | string | `1` | Diduga: 1 = gaji induk. Perlu daftar kode lengkap (gaji ke-13, THR, susulan, dll — CLAUDE.md §12 minta sistem tahu "komponen tidak dikenali") |
| **H** | **nip** | **NIP pegawai** | string, 18 digit | `195708201985031004` | **Identifier utama** sesuai §11 CLAUDE.md. Selalu 18 digit di contoh. |
| I | nmpeg | Nama pegawai | string | `PROF.DRS. [NAMA] M.SC. PH.D.` | **Punya trailing spasi panjang (fixed-width)** — wajib `trim()` saat import |
| J | kdduduk | Kode kedudukan pegawai | string, 2 digit | `01` | |
| K | kdgol | Kode golongan | string, 2 digit | `45` | Mis. 45 mungkin = IV/a. Perlu tabel kode golongan resmi jika mau ditampilkan sebagai label |
| L | npwp | NPWP | string, 15 digit | `[DIMASK]` | **Data sensitif** |
| M | nmrek | Nama pemilik rekening | string | `[NAMA]` | Trailing spasi, sama seperti nmpeg |
| N | nm_bank | Nama bank (cabang) | string | `PT.BANK RAKYAT INDONESIA (Persero) Tbk. KC SURAKARTA SUDIRMAN` | Trailing spasi |
| O | rekening | Nomor rekening | string | `[DIMASK]` | **Data sensitif.** Bukan untuk transfer (§1 CLAUDE.md — bukan sistem payroll banking), tapi mungkin perlu ditampilkan di slip sebagai info. Perlu keputusan: apakah field ini disimpan sama sekali, atau di-drop saat import karena di luar kebutuhan? |
| P | kdbankspan | Kode bank (SPAN) | string | `520002000990` | |
| Q | nmbankspan | Nama bank (SPAN) | string | `BANK RAKYAT INDONESIA` | |
| R | kdpos | Kode pos | string, 5 digit | `57126` | |
| S | kdnegara | Kode negara | string, 2 huruf | `ID` | |
| T | kdkppn | Kode KPPN | string, 3 digit | `028` | |
| U | tipesup | Tipe suplier | string, 2 digit | `03` | |
| V | gjpokok | **Gaji pokok** | integer (rupiah) | `6373200` | Komponen penghasilan |
| W | tjistri | Tunjangan istri/suami | integer | `637320` | Komponen penghasilan |
| X | tjanak | Tunjangan anak | integer | `0` | Komponen penghasilan |
| Y | tjupns | Tunjangan umum PNS | integer | `0` | Komponen penghasilan |
| Z | tjstruk | Tunjangan struktural | integer | `0` | Komponen penghasilan |
| AA | tjfungs | Tunjangan fungsional | integer | `1350000` | Komponen penghasilan |
| AB | tjdaerah | Tunjangan daerah | integer | `0` | Komponen penghasilan |
| AC | tjpencil | Tunjangan pengabdian(?) | integer | `0` | Komponen penghasilan — nama field ambigu, perlu konfirmasi |
| AD | tjlain | Tunjangan lainnya | integer | `0` | Komponen penghasilan |
| AE | tjkompen | Tunjangan kompensasi | integer | `0` | Komponen penghasilan |
| AF | pembul | Pembulatan | integer | `81` | Komponen penghasilan (nilai kecil, pembulatan gaji) |
| AG | tjberas | Tunjangan beras | integer | `144840` | Komponen penghasilan |
| AH | tjpph | Tunjangan PPh | integer | `85054` | Komponen penghasilan — **nilainya sama persis dengan `potpph` (AL)** pada baris yang sama (lihat §3) |
| AI | potpfkbul | Potongan PFK bulanan | integer | `0` | Komponen potongan (pusat) |
| AJ | potpfk2 | Potongan PFK 2% | integer | `0` | Komponen potongan (pusat) |
| AK | potpfk10 | Potongan PFK 10% | integer | `560841` | Komponen potongan (pusat) — biasanya iuran pensiun/Taspen |
| AL | potpph | Potongan PPh | integer | `85054` | Komponen potongan (pusat) |
| AM | potswrum | Potongan sewa rumah | integer | `0` | Komponen potongan (pusat) |
| AN | potkelbtj | Potongan kelebihan tunjangan | integer | `0` | Komponen potongan (pusat) |
| AO | potlain | Potongan lainnya | integer | `0` | Komponen potongan (pusat) |
| AP | pottabrum | Potongan tabungan perumahan | integer | `0` | Komponen potongan (pusat) |
| **AQ** | **bersih** | **Gaji bersih (versi pusat)** | integer | `7824600` | Lihat rumus §3 — **ini BUKAN "Total Penghasilan" mentah**, sudah dikurangi seluruh potongan pusat + BPJS |
| AR | sandi | Kode sandi/hash internal | string | `0021005c` | Tidak jelas kegunaannya untuk sistem fakultas |
| AS | kdkawin | Kode status kawin | string, 4 digit | `1100` | |
| AT | kdjab | Kode jabatan | string, 5 digit | `06901` | |
| AU | thngj | Tahun gaji jabatan(?) | string | `""` | Selalu kosong di contoh |
| AV | kdgapok | Kode gaji pokok/golongan gaji | string, 4 karakter | `4E32` | |
| AW | bpjs | Potongan BPJS | integer | `120000` | **Bukan komponen `pot*` di atas, tapi ikut mengurangi `bersih` (AQ)** — lihat rumus §3 |
| AX | bpjs2 | Potongan BPJS (2)? | integer | `0` | Selalu 0 di contoh — makna belum jelas |

## 3. Rumus yang terverifikasi dari data contoh

Dengan menjumlahkan manual seluruh baris, rumus berikut **cocok persis** untuk semua 7 baris:

```text
Total Penghasilan Kotor (pusat)
  = gjpokok + tjistri + tjanak + tjupns + tjstruk + tjfungs + tjdaerah
  + tjpencil + tjlain + tjkompen + pembul + tjberas + tjpph

Total Potongan (pusat)
  = potpfkbul + potpfk2 + potpfk10 + potpph
  + potswrum + potkelbtj + potlain + pottabrum + bpjs

bersih (AQ) = Total Penghasilan Kotor (pusat) − Total Potongan (pusat)
```

Contoh verifikasi (baris 2, Suranto):
`(6.373.200+637.320+1.350.000+81+144.840+85.054) − (560.841+85.054+120.000) = 8.590.495 − 765.895 = 7.824.600` ✓ sama dengan `AQ2`.

**Kegunaan:** rumus ini bisa dipakai sebagai validasi "Total tidak sesuai" (§12 CLAUDE.md) saat import — jika hasil hitung ulang tidak sama dengan `bersih` pada file, baris ditandai error.

## 4. Temuan penting: makna "Total Penghasilan" di CLAUDE.md §3.1/§5 perlu diperjelas

CLAUDE.md §5 mendefinisikan:

```text
Total Penghasilan − Total Potongan (fakultas) = Gaji Bersih
```

Setelah mencocokkan file ini dengan `potongan_fakultas.xlsx` (lihat `docs/excel-potongan.md` §4), ditemukan bahwa kolom **"Gaji Kotor" pada file potongan fakultas (kolom V) sama persis nilainya dengan kolom `bersih` (AQ) di file pusat ini** — bukan dengan "Total Penghasilan Kotor" hasil penjumlahan komponen di §3.

Artinya alur uang yang sebenarnya:

```text
Penghasilan Kotor (pusat)
  − Potongan Pusat (PFK, PPh, BPJS, dst — AI..AP, AW)
  = "bersih" (AQ)                              <- ini yang disebut fakultas sbg "Gaji Kotor"
  − Potongan Fakultas (koperasi, musola, dst)
  = Sisa Gaji (yang diterima pegawai)
```

**Rekomendasi (perlu dikonfirmasi ke fakultas):** field `Total Penghasilan` yang dipakai sistem sebagai basis perhitungan "Gaji Bersih" versi fakultas (§5 CLAUDE.md) **harus mengacu ke kolom `bersih` (AQ)**, bukan ke hasil penjumlahan mentah komponen penghasilan. Penjumlahan komponen (gjpokok, tjistri, dst.) tetap disimpan sebagai rincian/histori (untuk ditampilkan di slip gaji §18), tapi angka dasar perhitungan potongan fakultas adalah `bersih` dari pusat.

Ini **tidak mengubah nilai sumber pusat** (sesuai §3.1 — sistem tidak boleh mengubah nilai sumber), hanya menentukan kolom mana dari data pusat yang menjadi input ke tahap "Proses Gaji" (§15).

## 5. Kualitas data / hal yang perlu ditangani saat import

1. **Trailing whitespace** pada field teks (`nmpeg`, `nmrek`, `nm_bank`, `rekening`, `kdsubanak`) — hasil dari format fixed-width sumber asli. Wajib di-`trim()` sebelum disimpan/dibandingkan.
2. Seluruh kolom numerik tersimpan sebagai angka Excel biasa (bukan teks berformat rupiah), semuanya bilangan bulat (tidak ada desimal) — cocok untuk tipe `bigint`/`decimal(15,2)` sesuai §29 CLAUDE.md.
3. `nip` selalu 18 digit di contoh — tapi harus divalidasi sebagai **string**, bukan number (jika dibaca sebagai number, leading-zero NIP bisa hilang — meski di contoh tidak ada NIP berawalan 0, ini tetap risiko yang harus dicegah di parser Excel).
4. Kolom `rekening`, `npwp`, `nmrek` adalah **data sensitif** — perlu keputusan apakah disimpan di database sama sekali (lihat §30 keamanan CLAUDE.md), mengingat sistem ini eksplisit **bukan** sistem payroll banking (§1, §6). Kemungkinan cukup diabaikan/tidak diimpor.
5. Data contoh hanya mencakup 7 pegawai dan 1 periode (Juni 2026) dari 1 satker/unit — **belum merepresentasikan variasi penuh** (mis. pegawai dengan `kdjns` selain 1, pegawai non-dosen/tendik, potongan tabungan perumahan/sewa rumah terisi, dll).

## 6. Pertanyaan yang perlu dikonfirmasi ke pihak fakultas/pusat

1. Apakah struktur 50 kolom ini **selalu identik** setiap bulan, atau bisa bertambah/berkurang kolom (mis. saat ada THR/gaji ke-13 yang formatnya beda)?
2. Apakah `kdjns` punya nilai lain selain `1`, dan apa artinya masing-masing?
3. Apakah kolom `rekening`/`npwp`/`nmrek` perlu disimpan di sistem, atau cukup diabaikan karena di luar cakupan (§6 CLAUDE.md: bukan payroll banking)?
4. Apakah field `Total Penghasilan` yang dipakai sebagai basis §5 memang `bersih` (AQ), sesuai temuan §4 di atas, atau ada definisi lain yang dimaksud fakultas?
5. Apakah satu file Excel pusat selalu berisi **satu satker/unit** saja (`kdanak` selalu satu nilai), atau bisa berisi banyak unit sekaligus dalam satu file (relevan untuk Master Unit §11.1 dan filter per unit di Dashboard §10)?
6. Apa daftar kode `kdgol` (golongan) dan `kdjab` (jabatan) yang berlaku, jika perlu ditampilkan sebagai label (bukan kode mentah) di slip gaji?

## 7. Mapping awal ke Data Minimal (§3.1 CLAUDE.md)

| Field CLAUDE.md §3.1 | Kolom sumber |
|---|---|
| NIP | `H` (nip) |
| Nama | `I` (nmpeg), setelah trim |
| Gaji pokok | `V` (gjpokok) |
| Tunjangan | `W, X, Y, Z, AA, AB, AC, AD, AE, AG` (masing-masing jadi baris `salary_components` tersendiri — §28/§29, bukan digabung) |
| Total penghasilan lainnya | `AF` (pembul), `AH` (tjpph) — juga sebagai komponen tersendiri |
| Total penghasilan (basis proses gaji fakultas) | `AQ` (bersih) — **lih. temuan §4**, perlu konfirmasi |
