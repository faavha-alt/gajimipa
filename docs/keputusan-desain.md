# Keputusan Desain — Jawaban atas Pertanyaan Terbuka

Dokumen ini menjawab seluruh pertanyaan yang terkumpul di `docs/excel-gaji-pusat.md`, `docs/excel-potongan.md`, `docs/pemetaan-field-gaji.md`, `docs/actors.md`, dan `docs/workflow.md`, supaya STEP 4 (finalisasi database) bisa dimulai tanpa menunggu konfirmasi fakultas lebih dulu.

**Status:** keputusan kerja (working decision) yang diambil oleh tim pengembang, bukan konfirmasi resmi dari fakultas. Setiap butir diberi label tingkat keyakinan:

- 🟢 **Tinggi** — didukung bukti langsung dari data contoh (dihitung/diverifikasi), aman dijadikan final.
- 🟡 **Asumsi kerja** — keputusan praktis yang masuk akal, dirancang defensif (sistem akan mendeteksi & menolak dengan jelas kalau asumsi ini salah, bukan diam-diam salah proses). Perlu dikonfirmasi ke fakultas saat ada kesempatan, tapi tidak memblokir pengembangan.
- 🔵 **Keputusan produk** — pilihan desain yang murni keputusan tim (tidak ada "jawaban benar" dari data), didokumentasikan supaya konsisten.

Kalau nanti jawaban fakultas berbeda dari keputusan di sini, cukup update dokumen ini + migration terkait — bukan mendesain ulang dari nol, karena semua keputusan di bawah dirancang supaya mudah diubah (lihat catatan "Cara ubah" di tiap butir bila relevan).

---

## A. Dari `docs/excel-gaji-pusat.md` §6

### A1. Apakah struktur 50 kolom pusat selalu identik tiap bulan?
🟡 **Asumsi kerja: struktur bisa berubah, sistem tidak boleh mengasumsikan tetap.**
Keputusan: tahap "Deteksi Struktur" (§12 CLAUDE.md) membandingkan urutan+nama 50 kolom file yang diupload terhadap definisi kolom yang di-declare di konfigurasi sistem. Kalau tidak cocok persis → import ditolak dengan pesan jelas kolom mana yang beda, bukan diam-diam mencoba tetap jalan. Definisi kolom disimpan sebagai data (bukan hardcode di banyak tempat) supaya gampang di-update kalau formatnya memang berubah.

### A2. Apakah `kdjns` punya nilai lain selain `1`?
🟡 **Asumsi kerja: kemungkinan ada nilai lain, jangan hardcode ke `1`.**
Keputusan: `kdjns` disimpan mentah di `salary_records`. Master data baru **"Jenis Gaji Pusat"** dibuat (pola sama seperti Master Jenis Potongan §4), didaftarkan minimal nilai `1 = Gaji Induk`. Baris dengan `kdjns` yang belum terdaftar di master → masuk kategori error "Komponen tidak dikenali" (§12), bukan diproses asal jalan.

### A3. Apakah kolom rekening/NPWP/nama rekening perlu disimpan?
🔵 **Keputusan produk: TIDAK disimpan.**
Sistem eksplisit bukan payroll banking (§1, §6 CLAUDE.md). Field `npwp`, `nmrek`, `nm_bank`, `rekening`, `kdbankspan`, `nmbankspan` **di-drop saat import**, tidak dipetakan ke tabel manapun. Mengurangi permukaan data sensitif tanpa fungsi yang jelas di 17 modul (§9). Kalau nanti ada kebutuhan konkret, tinggal ditambahkan — lebih aman menambah daripada menghapus data sensitif yang sudah kadung tersimpan.

### A4. Apakah "Total Penghasilan" = kolom `bersih` (AQ)?
🟢 **Tinggi — dikunci final.**
Sudah diverifikasi matematis 100% (7/7 baris, lihat `docs/pemetaan-field-gaji.md` §3). `Total Penghasilan` yang jadi basis potongan fakultas = kolom `bersih` dari pusat, bukan penjumlahan komponen mentah.

### A5. Apakah 1 file pusat selalu 1 satker/unit?
🟡 **Asumsi kerja: tidak diasumsikan, desain tetap dukung multi-unit per file.**
Keputusan: import dikelompokkan berdasarkan `kdanak` **per baris**, bukan diasumsikan konstan di level file. Tidak menambah kompleksitas berarti, tapi menghindari desain yang rapuh kalau ternyata satu file bisa berisi beberapa unit.

### A6. Daftar kode `kdgol`/`kdjab`?
🔵 **Keputusan produk: tidak dibutuhkan untuk versi awal.**
`kdgol` dan `kdjab` disimpan sebagai kode mentah (string) di Master Pegawai dan di-snapshot per periode (lihat A/§B1). Slip gaji versi awal menampilkan kode mentah apa adanya. Tabel referensi kode→label bisa ditambah belakangan sebagai lookup opsional tanpa mengubah struktur inti.

---

## B. Dari `docs/pemetaan-field-gaji.md` §7

### B1. Apakah golongan/jabatan pegawai perlu di-snapshot per periode?
🟢 **Tinggi — dikunci final: YA, di-snapshot.**
Sesuai prinsip histori §29 CLAUDE.md. `kdgol`, `kdjab`, `kdgapok`, `kdkawin` disalin ke `salary_records` tiap periode (bukan hanya referensi live ke Master Pegawai), supaya slip periode lama tidak ikut berubah kalau pegawai naik pangkat di periode berikutnya. Master Pegawai tetap menyimpan nilai *terkini* untuk keperluan tampilan umum (mis. daftar pegawai aktif).

### B2/B3. Sudah dijawab di A3 dan D2 (di bawah).

---

## C. Dari `docs/excel-potongan.md` §9

### C1. Apakah NPP adalah identifier stabil untuk mapping ke NIP?
🟡 **Asumsi kerja: tidak diasumsikan stabil selamanya — mapping dikunci sekali, dipantau perubahannya.**
Keputusan: `employees` punya kolom `kode_npp_fakultas` (unique, nullable). Saat NPP pertama kali dilihat sistem, operator mengonfirmasi manual pasangannya dengan NIP di layar Preview import (dibantu tampilan Nama untuk verifikasi visual — §5 `docs/excel-potongan.md`). Setelah dikonfirmasi, mapping **dikunci** dan dipakai otomatis untuk import berikutnya. Kalau suatu saat NPP untuk NIP yang sama berubah di file baru, sistem mendeteksi ketidakcocokan dan meminta konfirmasi ulang manual — tidak re-mapping otomatis diam-diam.

### C2. Arti pasti kolom `Gota`, `Pralenan FMIPA`, `Biologi Mhs`?
🔵 **Keputusan produk: pakai label verbatim dari Excel, bukan menerka nama resmi.**
Master Jenis Potongan diisi dengan kode teknis (`IURAN_GOTA`, `PRALENAN_FMIPA`, `BIOLOGI_MHS`) dan **label tampilan = teks asli header Excel apa adanya** ("Gota", "Pralenan FMIPA", "Biologi Mhs"). Arti sesungguhnya tidak memengaruhi fungsi sistem (cuma perlu dijumlah & ditampilkan di bukti potongan), jadi tidak menghalangi pengembangan. Bisa diganti kapan saja lewat CRUD Master Jenis Potongan tanpa migrasi data.

### C3. Apakah cell kosong pada kolom potongan = Rp 0?
🟢 **Tinggi — dikunci final: YA.**
Konsisten dengan kolom lain yang eksplisit berisi `0` di baris lain untuk jenis potongan yang sama; tidak ada indikasi makna lain di data contoh.

### C4. Bagaimana pegawai pensiun di tengah periode (kasus baris 6) diproses?
🔵 **Keputusan produk.**
Kalau baris punya keterangan status (mis. "PENSIUN...") dan tanpa data nominal → baris **di-skip** dari import potongan (tidak membatalkan seluruh batch — beda dari baris error biasa, karena ini "memang tidak ada data", bukan "data salah"). Status Aktif pegawai di Master Pegawai diupdate terpisah oleh operator secara manual. Untuk gaji pusat: kalau pegawai pensiun sudah tidak muncul lagi di file bulan berikutnya, itu ditangani sebagai "pegawai tidak ada di file periode ini" (normal, bukan error).

### C5. Apakah rekap potongan yang diberikan mewakili seluruh pegawai?
🟡 **Asumsi kerja: tidak diasumsikan lengkap.**
Sistem tidak mewajibkan semua pegawai aktif muncul di file potongan tiap bulan (wajar kalau ada pegawai tanpa potongan apa pun bulan itu). Kalau ada pegawai aktif yang tidak muncul → tampilkan sebagai info/warning saat Preview, bukan error yang memblokir import.

### C6. Apakah format 15-kolom potongan konsisten tiap bulan?
🟡 **Asumsi kerja: sama seperti A1 — mapping disimpan sebagai konfigurasi, bukan posisi kolom hardcode.**
Operator bisa membuat/reuse "Template Mapping" per sumber data (mis. "Template Rekap Fakultas") yang memetakan kolom→Jenis Potongan, dan mengeditnya kalau kolom berubah — sejalan dengan §13 CLAUDE.md yang memang meminta mekanisme mapping fleksibel.

### C7. Kolom `W` (Sisa Gaji) — murni turunan atau bisa override manual dari file?
🟢 **Tinggi — dikunci final: SELALU dihitung sistem**, tidak pernah diambil mentah dari file. `W` di file sumber hanya dipakai sebagai **pembanding validasi** ("Total tidak sesuai", §12/§16) — kalau beda, ditandai error untuk dicek operator, bukan diikuti nilainya. Ini menjaga auditability (§30).

---

## D. Dari `docs/actors.md` (bagian penutup)

### D1. Apakah Pimpinan punya langkah approval formal?
🔵 **Keputusan produk: TIDAK ada approval formal terpisah di versi awal.**
Alur tetap `DRAFT → VERIFIKASI → FINAL` sesuai §17 CLAUDE.md. Pimpinan murni read-only (Dashboard + Laporan + data FINAL). Struktur status periode dirancang gampang diperluas (nilai `status` bertipe string/enum-terkelola, bukan boolean tunggal) supaya kalau nanti fakultas benar-benar minta approval formal, tinggal disisipkan sebagai status tambahan tanpa migrasi besar — tapi **tidak dibangun sekarang** karena belum ada kebutuhan konkret.

### D2. Pegawai login pakai NIP atau email?
🔵 **Keputusan produk: Email.**
`employees.email` (§11 CLAUDE.md, sudah wajib ada untuk notifikasi §22) dipakai sebagai kredensial login (`users.email`, mengikuti default Laravel Breeze). NIP tetap identifier utama di seluruh relasi data (FK), tapi bukan kredensial login — email lebih familiar untuk pegawai dibanding NIP 18 digit.

### D3. Apakah Operator boleh mengubah Master Unit & Master Jenis Potongan?
🔵 **Keputusan produk: Operator read-only, CRUD eksklusif Super Admin.**
Prinsip least-privilege — Operator perlu *melihat* master data ini untuk keperluan mapping saat import, tapi perubahan struktural (nama unit baru, jenis potongan baru) melalui Super Admin supaya tidak ada duplikasi/inkonsistensi penamaan dari banyak operator.

---

## E. Dari `docs/workflow.md` §7

### E1. Apakah ada transisi VERIFIKASI → DRAFT?
🟢 **Tinggi — dikunci final: YA, ditambahkan resmi ke alur.**
Verifikator bisa **"Tolak / Kembalikan ke Operator"** dari status VERIFIKASI, dengan alasan wajib diisi (dicatat di Audit Log — sama polanya dengan mekanisme revisi §17). Periode kembali ke DRAFT, lock dilepas. Ini kebutuhan operasional yang jelas dan tidak kontroversial — tanpa ini, satu kesalahan kecil butuh mekanisme revisi penuh yang sebenarnya didesain untuk data yang sudah FINAL.

### E2. Approval Pimpinan langkah formal terpisah?
Sudah dijawab di D1: tidak ada di versi awal.

### E3. Rekap Setoran Potongan — otomatis atau manual generate?
🔵 **Keputusan produk: manual generate**, kapan saja setelah periode FINAL (tombol "Generate" oleh Operator/Verifikator/Pimpinan). Konsisten dengan pola modul dokumen lain (slip gaji, bukti potongan — §18/§19 juga generate atas aksi eksplisit, bukan otomatis).

---

## F. Dampak ke STEP 4 (database)

Keputusan di atas menambah/mengubah beberapa hal terhadap struktur §28 CLAUDE.md (di luar koreksi 3-kategori komponen yang sudah dibahas di `docs/pemetaan-field-gaji.md` §6):

1. `employees` — tambah `kode_npp_fakultas` (nullable, unique), `status_pegawai_id` (FK ke Master Status Pegawai), `unit_id` (FK ke Master Unit), `email` (unique, dipakai login). **Tidak** ada kolom rekening/NPWP.
2. `salary_records` — tambah snapshot `golongan`, `jabatan`, `kode_gaji_pokok`, `status_kawin` (bukan hanya referensi ke `employees`).
3. Master data baru: **`jenis_gaji_pusat`** (untuk `kdjns`), selain `deduction_types` (Master Jenis Potongan §4) dan `units` (§11.1) dan `master_status_pegawai` (§11) yang sudah direncanakan.
4. `salary_periods.status` dirancang sebagai kolom terkelola (bukan boolean/enum kaku di kode) supaya gampang disisipi status baru nanti (D1) tanpa migrasi besar — tapi state machine yang **diimplementasikan sekarang** hanya `DRAFT → VERIFIKASI → FINAL → ARSIP` + transisi mundur `VERIFIKASI → DRAFT` (E1) + mekanisme revisi dari FINAL (§17).
5. Tabel **mapping template import** (mis. `import_column_mappings`) untuk menyimpan definisi kolom→field (gaji pusat) dan kolom→jenis potongan (potongan fakultas) sebagai data, bukan hardcode (A1, C6).
6. `users.email` dipakai untuk semua login (D2), termasuk pegawai — tidak perlu tabel/kolom kredensial terpisah.

Dokumen `docs/database.md` (ERD + migration plan) disusun berikutnya berdasarkan keputusan-keputusan ini.
