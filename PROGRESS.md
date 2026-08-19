# Progress — gaji

Dibuat: 2026-08-19

## Tasks

<!-- Format checklist standar: "- [ ] belum" / "- [x] selesai". Dibaca otomatis oleh Project Dashboard (http://100.94.175.72:4400/) untuk menghitung progres. -->
- [x] STEP 1 — Dokumen `docs/requirements.md`, `docs/actors.md`, `docs/workflow.md`
- [x] STEP 2 — Analisis Excel Gaji Pusat (`docs/excel-gaji-pusat.md`)
- [x] STEP 3 — Analisis Excel Potongan (`docs/excel-potongan.md`)
- [ ] Konfirmasi ke fakultas: pertanyaan terbuka di seluruh dokumen `docs/*.md` (lihat bagian "Pertanyaan yang perlu dikonfirmasi"/"Pertanyaan terbuka" di masing-masing file)
- [ ] STEP 4 — Finalisasi database (`docs/database.md`, ERD, migrations) — menunggu konfirmasi di atas
- [ ] STEP 5 — Project setup Laravel (Docker, MySQL, Blade, Livewire, Tailwind, Alpine, Auth, Role & Permission)

## Log sesi

### 2026-08-19
- Project diinisialisasi dari template.
- Menerima contoh data asli di `data_gaji/` (gaji pusat & potongan fakultas). File ini **tidak di-commit** — ditambahkan `.gitignore` karena berisi data pribadi/keuangan pegawai (NIP, rekening, NPWP, gaji).
- Analisis struktur kedua file Excel selesai, ditulis ke `docs/excel-gaji-pusat.md` dan `docs/excel-potongan.md`.
- Temuan penting yang memengaruhi desain: kolom `bersih` (pusat) = "Gaji Kotor" versi fakultas → jadi basis perhitungan potongan fakultas, bukan penjumlahan komponen mentah. File potongan fakultas **tidak punya kolom NIP** (hanya Nama + NPP internal) — perlu mekanisme mapping NPP↔NIP di Master Pegawai. Ditemukan kasus pegawai pensiun di tengah periode. Ada 15 jenis potongan dalam 1 file (bukan 1 file per jenis seperti asumsi awal §13).
- Semua temuan di atas masih **perlu dikonfirmasi ke pihak fakultas** sebelum STEP 4 (finalisasi database) dimulai.
- STEP 1 (dokumen kebutuhan) diselesaikan: `docs/requirements.md` (rangkuman FR/NFR), `docs/actors.md` (rincian 5 role + matriks akses per modul), `docs/workflow.md` (diagram siklus periode, alur import, alur revisi). Ketiganya mereferensikan temuan Excel di atas dan menandai pertanyaan tambahan (mis. apakah ada transisi VERIFIKASI→DRAFT, apakah Pimpinan punya approval formal, kredensial login Pegawai).
- **Belum boleh mulai coding** (CLAUDE.md §36) — STEP 4 (database final) menunggu jawaban fakultas atas seluruh pertanyaan terbuka yang terkumpul di STEP 1–3.

### 2026-08-19 (lanjutan) — Pemetaan field & verifikasi hitung ulang
- Dibuat `docs/pemetaan-field-gaji.md`: memisahkan field jadi 4 kelompok — (1) melekat ke Master Pegawai, (2) komponen penghasilan periodik dari pusat, (3) komponen potongan periodik dari pusat (PFK/PPh/BPJS — ternyata terpisah dari "Data Potongan" §4 CLAUDE.md yang dikelola fakultas), (4) komponen potongan periodik dari fakultas.
- Verifikasi dengan skrip (bukan manual) untuk **seluruh baris** kedua file: rumus `Total Penghasilan Kotor − Total Potongan Pusat = Bersih dari Pusat` cocok 7/7 baris; rumus `Bersih dari Pusat − Total Potongan Fakultas = Gaji Bersih Final` cocok 7/7 baris (1 baris pensiun dikecualikan, memang tidak ada data).
- **Temuan yang mengoreksi §28 CLAUDE.md:** struktur database perlu 3 kategori komponen (bukan 2) — `salary_components` harus punya sub-kategori PENGHASILAN dan POTONGAN_PUSAT (keduanya read-only, dari import pusat), terpisah dari `deduction_records` yang murni POTONGAN_FAKULTAS (editable). Lihat `docs/pemetaan-field-gaji.md` §6.
- Pertanyaan baru: apakah golongan/jabatan pegawai perlu di-snapshot per periode; apakah field rekening/NPWP perlu disimpan; nama resmi 3 jenis potongan yang header-nya terpotong (Gota, Pralenan, Biologi Mhs).
