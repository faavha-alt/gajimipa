# Progress — gaji

Dibuat: 2026-08-19

## Tasks

<!-- Format checklist standar: "- [ ] belum" / "- [x] selesai". Dibaca otomatis oleh Project Dashboard (http://100.94.175.72:4400/) untuk menghitung progres. -->
- [x] STEP 1 — Dokumen `docs/requirements.md`, `docs/actors.md`, `docs/workflow.md`
- [x] STEP 2 — Analisis Excel Gaji Pusat (`docs/excel-gaji-pusat.md`)
- [x] STEP 3 — Analisis Excel Potongan (`docs/excel-potongan.md`)
- [x] Jawab seluruh pertanyaan terbuka sebagai keputusan desain kerja (`docs/keputusan-desain.md`) — dipakai untuk lanjut STEP 4 tanpa menunggu konfirmasi fakultas; masih perlu divalidasi ulang ke fakultas saat ada kesempatan (ditandai 🟡 di dokumen)
- [ ] STEP 4 — Finalisasi database (`docs/database.md`, ERD, migrations) — berdasarkan `docs/keputusan-desain.md` §F
- [x] STEP 5 — Project setup Laravel (MySQL, Blade, Livewire, Tailwind, Alpine, Auth/Breeze, spatie/laravel-permission)
- [ ] Set document root vhost server dev ke `public/` langsung (workaround salinan manual masih dipakai — lihat log 2026-08-19 lanjutan 3)
- [x] Domain `gaji.mipa.uns.ac.id` terverifikasi live & reachable dari internet

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

### 2026-08-19 (lanjutan 2) — STEP 5: Project setup Laravel di server dev
- Verifikasi ulang terprogram (bukan manual): seluruh 50 kolom `dasar_acuan_univ.xlsx` dan 24 kolom `potongan_fakultas.xlsx` sudah punya referensi di `docs/excel-*.md` — cocok 100%, hanya 1 typo spasi kecil yang diperbaiki.
- Deploy key baru dibuat untuk login server dev via SSH (`gajimipa-dev` alias di `~/.ssh/config` lokal), terhubung ke `203.6.149.150:1103` user `gajimipa`. Terverifikasi: OS Debian 13 trixie, PHP 8.4.23, Composer 2.10, MySQL 8.4.10 (kredensial DB `gajimipa`/`gajimipa` sudah tervalidasi jalan).
- Laravel 13 di-scaffold langsung di server (`~/htdocs/gaji.mipa.uns.ac.id/`, folder situs yang sudah disiapkan panel hosting), lalu ditarik turun ke repo lokal via tar-over-ssh dan digabung (tanpa menimpa `CLAUDE.md`/`docs/`/`.gitignore` yang sudah ada).
- Package terpasang: `livewire/livewire` (resolve ke v3.8.5 karena constraint Breeze), `spatie/laravel-permission` v8 (migration & config dipublish), `laravel/breeze` stack Livewire (scaffolding auth Blade+Livewire+Alpine+Tailwind).
- Node.js diinstal di server via `nvm` (user-space, tanpa root) karena server awalnya tidak punya Node — dipakai untuk `npm install && vite build` (Tailwind ter-compile, `public/build/` dihasilkan).
- `.env` dikonfigurasi: DB ke MySQL asli (`gajimipa`), `APP_TIMEZONE=Asia/Jakarta` (di-wire ke `config/app.php`, sebelumnya hardcode UTC), `APP_LOCALE=id`, `APP_URL=https://gaji.mipa.uns.ac.id`. Migrasi dasar (users, cache, jobs, permission tables) sukses jalan ke DB asli. `storage:link` sudah dijalankan.
- Struktur folder `app/` dibuat sesuai target §27 CLAUDE.md: `Actions/{Salary,Deduction,Import,Report}`, `Services/{Salary,Deduction,Import,Payslip,Report}`, `Livewire/{Dashboard,Employees,Salary,Deductions,Imports,Payslips,Reports}`, `Policies`, `Jobs`, `Notifications`, `Support` (masih kosong, isi `.gitkeep`).
- **Belum bisa diverifikasi dari luar:** domain publik `gaji.mipa.uns.ac.id` timeout/HTTP 000 saat di-curl dari server itu sendiri — perlu dicek DNS/firewall/status panel. Document root vhost panel hosting juga **belum diarahkan ke `public/`** (di luar akses shell user `gajimipa`, `/etc/nginx` permission denied, tidak ada passwordless sudo) — **user perlu mengatur ini manual lewat panel hosting**, kalau tidak Laravel tidak akan ter-serve dengan benar (akan expose seluruh source root, bukan cuma `public/`).
- Server ternyata bukan Docker/CasaOS seperti asumsi §25 CLAUDE.md, melainkan hosting terkelola (struktur `htdocs/<domain>/`, `logs/nginx`, `logs/php`, `.varnish-cache` khas panel seperti CloudPanel) dengan PHP-FPM & Nginx dikelola di luar akses shell. Env `Docker` tetap relevan untuk deployment produksi nanti (§20), tapi dev server saat ini pakai jalur native PHP-FPM milik panel.

### 2026-08-19 (lanjutan 3) — Workaround document root tanpa akses panel
- Karena tidak ada akses untuk mengarahkan vhost ke `public/` (`/etc/nginx` permission denied, tidak ada sudo), dipakai trik standar hosting terbatas: **kode aplikasi dipindah** ke `~/gajimipa-app/` (di luar folder yang dilayani web server), dan **isi `public/`** (index.php, build/, favicon, robots.txt, dst.) **disalin ke `~/htdocs/gaji.mipa.uns.ac.id/`** (folder yang benar-benar dilayani nginx). `.well-known/` (dipakai panel untuk SSL) dipertahankan di lokasi asal.
- `index.php` hasil salinan di-edit manual: path `__DIR__.'/../vendor/autoload.php'` dan `__DIR__.'/../bootstrap/app.php'` diganti jadi path absolut ke `/home/gajimipa/gajimipa-app/...`, karena app sekarang tidak lagi satu folder dengan `public/`-nya.
- Symlink `public/storage` dibuat ulang manual (`ln -s /home/gajimipa/gajimipa-app/storage/app/public ...`) karena symlink lama jadi tidak valid setelah pemindahan.
- **Bug ditemukan & diperbaiki:** `livewire.js` 404 di nginx — server ini punya rule yang mencegat file `.js` sebagai static file sebelum sampai ke PHP, padahal Livewire biasanya menyajikannya lewat route dinamis. Diperbaiki dengan `php artisan livewire:publish --assets` (menjadikannya file statis di `/vendor/livewire/livewire.js`) lalu disalin ke `htdocs/`.
- **Diverifikasi hidup dari internet**: log nginx menangkap request asli dari client eksternal ke `/login`, `/register`, dll — situs sudah publicly reachable. Smoke test semua rute utama (`/`, `/login`, `/register`, asset Tailwind, asset Livewire, favicon) → HTTP 200.
- **Konsekuensi arsitektur untuk diingat:** `htdocs/gaji.mipa.uns.ac.id/` sekarang adalah **salinan turunan** dari `~/gajimipa-app/public/`, bukan sumber asli. Setiap kali ada `npm run build` baru atau perubahan file di `public/` (favicon, robots.txt, dll), folder `htdocs/gaji.mipa.uns.ac.id/{build,vendor,favicon.ico,robots.txt,index.php}` **harus disinkron ulang manual** — ini bukan solusi permanen. Solusi permanen tetap: minta admin panel mengarahkan document root vhost langsung ke `~/gajimipa-app/public/`, lalu hapus workaround ini.
- **Insiden kedua kali**: proses tarik-kode (tar-over-ssh + `cp -a` overwrite) sempat **menimpa ulang `.gitignore`** dengan versi default Laravel tanpa exclude `data_gaji/` — kali ini tertangkap **sebelum** `git add`, jadi tidak pernah masuk staging/commit sama sekali. `.gitignore` sudah diperbaiki lagi. **Perlu kehati-hatian permanen**: setiap kali sync dari server, cek `.gitignore` dulu sebelum `git add -A`.

### 2026-08-19 (lanjutan 4) — Jawab semua pertanyaan terbuka sebagai keputusan desain
- Dibuat `docs/keputusan-desain.md`: menjawab seluruh ~22 pertanyaan terbuka yang terkumpul di `excel-gaji-pusat.md`, `excel-potongan.md`, `pemetaan-field-gaji.md`, `actors.md`, `workflow.md` — masing-masing diberi label keyakinan (🟢 tinggi/terverifikasi data, 🟡 asumsi kerja defensif, 🔵 keputusan produk).
- Keputusan kunci: rekening/NPWP pegawai TIDAK disimpan (di luar cakupan §6); NPP fakultas dipetakan ke NIP sekali lalu dikunci (bukan re-match tiap saat); golongan/jabatan pegawai di-snapshot per periode; login pegawai pakai email; ada transisi VERIFIKASI→DRAFT (tolak/kembalikan) yang ditambahkan resmi ke alur; Rekap Setoran generate manual; tidak ada approval formal Pimpinan di versi awal.
- §F dokumen ini merinci dampak konkret ke skema `docs/database.md` yang akan disusun di STEP 4: kolom baru di `employees`/`salary_records`, master data baru `jenis_gaji_pusat`, tabel mapping template import.
- Ini keputusan tim pengembang, bukan konfirmasi resmi fakultas — tetap perlu divalidasi ulang saat ada kesempatan, tapi tidak lagi memblokir STEP 4.
