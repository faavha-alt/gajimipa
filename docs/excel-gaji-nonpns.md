# Analisis Excel Gaji Non-PNS

Status: **Draft awal berdasarkan 1 contoh file.** Perlu dikonfirmasi ke pihak fakultas sebelum dijadikan dasar mapping/import final (lih. bagian "Pertanyaan yang perlu dikonfirmasi").

## 1. Sumber

- File contoh: `data_gaji/DasaracuanNonPNS.xls` (tidak di-commit ke git — berisi data pribadi & keuangan pegawai asli, sama seperti `data_gaji/` lainnya).
- Format file **`.xls` lama** (Composite Document File V2 / BIFF), bukan `.xlsx` — berbeda dari 2 file contoh sebelumnya. `Excel::toCollection()` (maatwebsite/excel) tetap membacanya transparan, tidak perlu penanganan khusus.
- 1 sheet, header 1 baris, 35 baris data pegawai, seluruhnya periode **Agustus 2026** (`TAHUNBULAN=202608`), unit **FMIPA** (satu nilai saja di seluruh contoh — tidak per-prodi).
- **Ini bukan variasi kecil dari format PNS (`docs/excel-gaji-pusat.md`) — ini format yang sama sekali berbeda**: nama kolom, jumlah komponen, dan kemungkinan besar *sumber/pembuat file* juga berbeda (lihat §5).

## 2. Struktur Kolom

25 kolom, header huruf besar dengan underscore (beda gaya penamaan dari format PNS yang huruf kecil singkatan).

| Kolom | Nama Header | Arti (dugaan) | Tipe | Contoh | Catatan |
|---|---|---|---|---|---|
| 0 | NO | Nomor urut baris | integer | `1` | |
| 1 | TAHUNBULAN | Periode, gabungan tahun+bulan | string/int, 6 digit `YYYYMM` | `202608` | **Beda dari format PNS** yang punya `bulan`/`tahun` terpisah — perlu parsing `substr` |
| 2 | IDPEGAWAI | ID internal pegawai | integer | `7863` | **Tidak cocok dengan `employees.kode_npp_fakultas`** pada 0 dari 35 baris — perlu klarifikasi ini ID dari sistem apa |
| 3 | NAMA | Nama pegawai | string | `Silvina Rosita Yulianti` | Tanpa trailing whitespace berlebih (beda dari format PNS) |
| 4 | NIP | Identifier pegawai | string, 16 digit | `2000071720250701` | **Bukan format NIP 18-digit standar PNS** — polanya mirip `DDMMYYYY` (tanggal lahir?) + `YYYYMMDD` (tanggal SK?). Perlu konfirmasi apakah ini benar NIP PPPK/Non-PNS resmi atau ID internal fakultas. 30 dari 35 nilai ini **cocok** dengan `employees.nip` yang sudah ada di Master Pegawai — jadi identifier ini kemungkinan besar memang dipakai konsisten sebagai `nip` di sistem saat ini. |
| 5 | UNIT | Unit kerja | string | `FMIPA` | Hanya 1 nilai di contoh (level fakultas, bukan prodi) |
| 6 | STATUS | Status (kepegawaian?) | string, 2 huruf | `AK` | Hanya 1 nilai di seluruh contoh (`AK`) — diduga "Aktif", belum terkonfirmasi ada nilai lain |
| 7 | JENIS | Jenis pegawai | string | `Tenaga Pendidik` / `Tenaga Kependidikan` | 2 nilai unik di contoh — **selaras dengan Master Status Pegawai (§11 CLAUDE.md, dosen vs tendik)** |
| 8 | FUNGSIONAL | Jabatan fungsional | string, boleh kosong | `Tenaga Pengajar`, `Asisten Ahli`, `Lektor`, `` (null untuk Tendik) | 3 nilai terisi + kosong. Analog `jabatan_snapshot` pada format PNS |
| 9 | ISTRI/SUAMI_TERTANGGUNG | Status pasangan tertanggung | string | `YA` / `TIDAK` | Tidak ada di format PNS |
| 10 | JUMLAH_ANAK_TERTANGGUNG | Jumlah anak tertanggung | integer | `0` | Tidak ada di format PNS |
| 11 | TOTAL_KELUARGA | Total anggota keluarga tertanggung | integer | `1` | Tidak ada di format PNS |
| 12 | GAJI_POKOK | Gaji pokok | decimal | `2729500` | Komponen penghasilan |
| 13 | TUNJ_ISTRI | Tunjangan istri/suami | decimal | `0` | Komponen penghasilan |
| 14 | TUNJ_FUNGSIONAL | Tunjangan fungsional | decimal | `0` | Komponen penghasilan |
| 15 | TUNJ_ANAK | Tunjangan anak | decimal | `0` | Komponen penghasilan |
| 16 | TUNJ_BERAS | Tunjangan beras | decimal | `72420` | Komponen penghasilan |
| **17** | **GAJI_KOTOR** | **Total penghasilan kotor** | decimal | `2801920` | Lihat rumus §3 |
| 18 | BANK | Nama bank | string | `BTN` | Data sensitif |
| 19 | NO_REKENING | Nomor rekening | string | (terisi) | **Data sensitif** — sama pertimbangan seperti format PNS §30 |
| 20 | NPWP | NPWP | string | (terisi) | **Data sensitif** |
| 21 | CATATAN | Keterangan bebas | string, boleh kosong | `` | |
| 22 | POT_PPH21 | Potongan PPh 21 | decimal | `0` | Komponen potongan — **selalu 0 di seluruh 35 baris contoh** |
| 23 | POT_IWP | Potongan iuran wajib pegawai | decimal | `0` | Komponen potongan — **selalu 0 di seluruh 35 baris contoh** |
| 24 | POT_BPJS | Potongan BPJS | decimal | `0` | Komponen potongan — **selalu 0 di seluruh 35 baris contoh** |

## 3. Rumus yang terverifikasi dari data contoh

Diverifikasi terhadap **seluruh 35 baris** (bukan sampel):

```text
GAJI_KOTOR = GAJI_POKOK + TUNJ_ISTRI + TUNJ_FUNGSIONAL + TUNJ_ANAK + TUNJ_BERAS
```

Cocok persis di 35/35 baris, tanpa selisih.

**Berbeda dari format PNS**: tidak ada kolom "bersih setelah potongan pusat" di sini — `GAJI_KOTOR` adalah penghasilan kotor murni, dan seluruh kolom `POT_*` (PPh21, IWP, BPJS) bernilai **0 di semua baris contoh**. Belum bisa dipastikan apakah ini karena:
- (a) pegawai Non-PNS di fakultas ini memang belum/tidak dipotong PPh21/IWP/BPJS pusat, atau
- (b) kolom ini memang selalu diisi kemudian secara manual/terpisah dan kebetulan contoh ini kosong, atau
- (c) potongan Non-PNS sepenuhnya ditangani lewat modul Data Potongan Fakultas (§13/§14 CLAUDE.md), bukan lewat file ini.

## 4. Kualitas data / kecocokan dengan Master Pegawai

- 35 baris, 35 NIP unik (tidak ada duplikat).
- **30 dari 35 NIP sudah cocok** dengan `employees.nip` yang ada di Master Pegawai saat ini.
- **5 NIP tidak ditemukan** di Master Pegawai — kemungkinan pegawai baru yang belum didaftarkan:

  | NIP | Nama | IDPEGAWAI |
  |---|---|---|
  | 1996040420250701 | Muhammad Fahmy Nadhif, S.Kom., M.T. | 7872 |
  | 1983030220161001 | Heri Prasetyo, S.Kom, M.Sc.Eng., Ph.D. | 6054 |
  | 1977091020080301 | Muchamad Taslim | 2858 |
  | 1983102820150401 | Tetra Setya Handayani, S.Pd. | 2899 |
  | 1986100620150401 | Muhammad Firdaus, S.Pd. | 3052 |

  Sesuai §12 CLAUDE.md ("NIP tidak ditemukan" adalah salah satu validasi wajib), baris ini akan ditolak oleh alur import standar sampai kelima pegawai ini ditambahkan ke Master Pegawai lebih dulu.
- `IDPEGAWAI` tidak cocok dengan field `kode_npp_fakultas` pada satu pun baris — field ini kemungkinan berasal dari sistem lain (mis. SIMPEG) dan perlu diklarifikasi apakah perlu disimpan (mis. sebagai `id_simpeg` yang sudah ada di skema Employee) atau diabaikan.

## 5. Sumber data: dikonfirmasi oleh user (2026-08-20)

**Dikonfirmasi**: file ini diterbitkan oleh **sistem universitas** (bukan buatan ad-hoc fakultas), memang khusus untuk pegawai Non-PNS — sistem penggajian Non-PNS tingkat universitas yang terpisah dari sistem GPP pemerintah yang menghasilkan format PNS (`docs/excel-gaji-pusat.md`).

Konsekuensi: data ini tetap termasuk kategori **"Data Pusat"** dalam arti §3.1 CLAUDE.md — sumber resmi eksternal (sistem universitas), bukan data yang dikelola bebas oleh fakultas. Perlakuannya **sama seperti format PNS**: immutable/snapshot, tidak boleh diubah langsung, koreksi wajib lewat mekanisme revisi (§17). Perbedaannya hanya di *format file dan sistem sumbernya* (GPP pemerintah untuk PNS vs sistem penggajian Non-PNS universitas), bukan di level kepercayaan/otoritas datanya.

## 6. Pertanyaan yang masih perlu dikonfirmasi ke pihak fakultas

1. ~~Siapa yang menerbitkan file ini?~~ **Sudah dikonfirmasi (§5)**: sistem penggajian Non-PNS universitas.
2. Apakah struktur 25 kolom ini **selalu identik** setiap bulan, atau bisa berubah?
3. Field `NIP` (16 digit) — apakah ini NIP PPPK/Non-PNS resmi yang diterbitkan BKN, atau nomor identitas internal buatan fakultas? (30/35 sudah cocok dengan data Master Pegawai saat ini, jadi kemungkinan sudah dipakai konsisten — tapi perlu dipastikan maknanya untuk dokumentasi.)
4. Apa arti kode `STATUS = 'AK'`? Apakah ada nilai lain (mis. non-aktif, cuti, berhenti)?
5. Field `IDPEGAWAI` — berasal dari sistem apa (SIMPEG?), dan perlu disimpan di Master Pegawai atau tidak?
6. Kolom `POT_PPH21`, `POT_IWP`, `POT_BPJS` selalu 0 di contoh — apakah memang belum berlaku untuk Non-PNS, atau nanti akan terisi di periode lain / diisi dari sumber lain?
7. 5 pegawai dalam file belum ada di Master Pegawai (lih. §4) — apakah memang pegawai baru yang perlu didaftarkan dulu, atau ada kesalahan penulisan NIP di salah satu sisi (file vs Master Pegawai)?
8. Apakah setiap bulan akan ada **2 file terpisah** (PNS dari pusat + Non-PNS dari fakultas) yang digabung untuk 1 periode yang sama, atau keduanya dianggap periode/proses yang independen?

## 7. Mapping awal ke skema tujuan (`salary_records` / `salary_components`)

Skema tujuan (`SalaryRecord`, `SalaryComponent` — lih. `docs/pemetaan-field-gaji.md`) **cukup generik untuk menampung format ini tanpa migrasi baru**, karena `salary_components` menyimpan komponen sebagai baris dinamis (`kode_komponen`, `nama_komponen`, `nominal`), bukan kolom tetap.

| Field tujuan | Kolom sumber Non-PNS |
|---|---|
| `nip_snapshot` | `NIP` (kol. 4) |
| `nama_snapshot` | `NAMA` (kol. 3) |
| Komponen penghasilan (`kategori=PENGHASILAN`) | `GAJI_POKOK`, `TUNJ_ISTRI`, `TUNJ_FUNGSIONAL`, `TUNJ_ANAK`, `TUNJ_BERAS` — masing-masing jadi 1 baris `salary_components` |
| `total_penghasilan_kotor` | `GAJI_KOTOR` (kol. 17), atau hasil jumlah komponen (sudah terbukti sama, §3) |
| Komponen potongan pusat (`kategori=POTONGAN_PUSAT`) | `POT_PPH21`, `POT_IWP`, `POT_BPJS` |
| `total_potongan_pusat` | jumlah 3 kolom `POT_*` (selalu 0 di contoh) |
| `bersih_pusat` | `GAJI_KOTOR − total_potongan_pusat` (karena tidak ada kolom "bersih" eksplisit seperti format PNS) |
| — tidak dipetakan — | `IDPEGAWAI`, `STATUS`, `ISTRI/SUAMI_TERTANGGUNG`, `JUMLAH_ANAK_TERTANGGUNG`, `TOTAL_KELUARGA`, `CATATAN` — belum ada tujuan di skema saat ini, perlu keputusan (simpan sebagai snapshot tambahan, atau abaikan) |
| `unit_snapshot` | `UNIT` (selalu "FMIPA" — lebih kasar dari Master Unit per-prodi yang sudah ada) |
| Status Pegawai (Dosen/Tendik) | `JENIS` — berpotensi dipakai untuk **memvalidasi/menyinkronkan** `employees.status_pegawai_id`, bukan sekadar snapshot periodik |
| `jabatan_snapshot` | `FUNGSIONAL` |
| `bank`, `no_rekening`, `npwp` | Kolom sensitif — perlakuan sama seperti keputusan A3 (lih. `docs/keputusan-desain.md`) untuk format PNS |

## 8. Kesimpulan sementara

Format ini **tidak bisa ditangani sebagai variasi kecil** dari `SalaryImportService` yang ada (yang hard-code header PNS: `nip, nmpeg, bulan, tahun, gjpokok, bersih`). File Non-PNS akan otomatis ditolak validasi struktur saat ini karena hampir semua header wajib tersebut tidak ada.

Diperlukan keputusan arsitektur eksplisit sebelum coding — lihat pertanyaan strategi yang diajukan ke user di luar dokumen ini.
