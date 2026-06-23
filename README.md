# SIMPEG

SIMPEG adalah aplikasi Sistem Informasi Kepegawaian berbasis PHP dan MySQL/MariaDB. Aplikasi ini dipakai untuk mengelola data pegawai, riwayat jabatan, riwayat pendidikan, keluarga, diklat, sertifikasi, mutasi, user, dashboard, dan laporan kepegawaian.

Repo ini masih berbentuk aplikasi PHP klasik/prosedural dengan template AdminLTE. README ini dibuat sebagai catatan awal sebelum perombakan berikutnya: penambahan API internal, perluasan akses role user untuk mengelola data keluarga sendiri, dan rencana upgrade runtime PHP ke versi yang lebih baru.

## Gambaran Aplikasi

Alur utama aplikasi:

1. `index.php` menampilkan halaman login.
2. `pages/login/act-login.php` memproses login dan menyimpan session.
3. Setelah login, user diarahkan ke `home-admin.php`.
4. `home-admin.php` memuat layout dari folder `templates/` dan halaman modul melalui `dist/functions.php`.
5. Mapping halaman ada di fungsi `getPage($page)` pada `dist/functions.php`.

Role yang saat ini terlihat di aplikasi:

- `admin`: akses utama untuk dashboard, master data pegawai, keluarga, jabatan, pendidikan, diklat, sertifikasi, laporan, user, dan konfigurasi aplikasi.
- `kepala`: akses dashboard cabang, data pegawai, dan laporan dengan konteks kantor/cabang.
- `user`: akses profil pegawai sendiri. Saat ini fokusnya masih melihat profil dan mengganti foto.

## Struktur Folder Penting

```text
.
+-- api/                         # Bootstrap API internal dan endpoint awal
+-- assets/                      # Asset tambahan aplikasi
+-- assets-eform/                # Asset untuk e-form kredit
+-- db/                          # Dump/struktur database SIMPEG
+-- docs/                        # Dokumentasi pengembangan, termasuk test API
+-- dist/                        # Koneksi database, helper, AdminLTE build, asset utama
+-- pages/                       # Modul aplikasi per fitur
|   +-- config/                  # Pengaturan aplikasi
|   +-- kantor/                  # Data kantor/unit kerja
|   +-- login/                   # Login/logout
|   +-- otorisasi/               # Approval perubahan data
|   +-- pegawai/                 # Data pegawai, profil, import, foto
|   +-- ref-biaya-pendidikan/    # Referensi/biaya pendidikan atau diklat
|   +-- ref-diklat/              # Data pelatihan/diklat
|   +-- ref-jabatan/             # Riwayat dan master jabatan
|   +-- ref-keluarga/            # Suami/istri, anak, orang tua
|   +-- ref-mutasi/              # Mutasi/penonaktifan pegawai
|   +-- ref-pendidikan/          # Pendidikan
|   +-- ref-sertifikasi/         # Sertifikasi
|   +-- report/                  # Laporan dan export
|   +-- tools/                   # Tool bantu/sinkronisasi
|   +-- user/                    # Manajemen user
+-- plugins/                     # Plugin frontend AdminLTE, Bootstrap, DataTables, dll
+-- templates/                   # Header, sidebar, footer layout
+-- uploads/                     # Upload foto/dokumen
+-- vendor/                      # Dependency Composer
+-- config.php                   # Base URL aplikasi
+-- dist/koneksi.php             # Koneksi MySQLi
+-- home-admin.php               # Shell halaman setelah login
+-- index.php                    # Halaman login
```

## Modul Utama

- Pegawai: daftar pegawai, tambah/edit pegawai, import Excel, detail pegawai, profil pegawai, ganti foto, perubahan ID pegawai, penonaktifan/mutasi.
- Keluarga: data suami/istri, anak, dan orang tua pegawai.
- Jabatan: riwayat jabatan, master jabatan, import data jabatan.
- Pendidikan: riwayat pendidikan pegawai.
- Diklat dan sertifikasi: data pelatihan, sertifikasi, import, dan laporan biaya/diklat.
- Laporan: nominatif pegawai, keadaan pegawai, formasi jabatan, rekap biaya/diklat, export/print.
- User: manajemen akun aplikasi dan sinkronisasi user dari data pegawai.
- Konfigurasi: nama aplikasi, logo, dan konfigurasi tampilan/aplikasi.

## Database

File database ada di folder `db/`. Tabel penting yang terlihat dari dump antara lain:

- `tb_pegawai`
- `tb_user`
- `tb_jabatan`
- `tb_kantor`
- `tb_suamiistri`
- `tb_anak`
- `tb_ortu`
- `tb_pendidikan`
- `tb_diklat`
- `tb_sertifikasi`
- `tb_mutasi`
- `tb_hukuman`
- `tb_config`
- `tb_log_aktivitas`
- `tb_edit_pending`
- `tb_notifikasi`

Koneksi database saat ini memakai MySQLi prosedural di `dist/koneksi.php`. Untuk setup lokal, sesuaikan nilai host, user, password, database, dan port pada file tersebut.

## Instalasi Lokal

Kebutuhan minimum yang dipakai repo saat ini:

- PHP dengan ekstensi `mysqli`
- MySQL/MariaDB
- Composer
- Web server Apache/XAMPP

Langkah umum:

1. Clone/copy repo ke folder web server, contoh `htdocs/DUMMY`.
2. Import database dari folder `db/`.
3. Sesuaikan koneksi database di `dist/koneksi.php`.
4. Sesuaikan base URL di `config.php`.
5. Jalankan Composer jika folder `vendor/` belum tersedia:

```bash
composer install
```

6. Buka aplikasi dari browser sesuai base URL lokal, contoh:

```text
http://localhost:8081/dummy/
http://localhost:8081/dummy/dashboard
http://localhost:8081/dummy/form-view-data-pegawai
```

## Dependency

Dependency Composer saat ini:

```json
{
  "phpoffice/phpspreadsheet": "^1.8"
}
```

Library ini dipakai untuk kebutuhan import/export Excel.

## Progress `dev-app`

Progress per 18 Juni 2026 di branch `dev-app`:

- [x] Menambahkan helper base URL di `config.php` untuk local dan server.
- [x] Menyiapkan routing bersih awal lewat `.htaccess`:
  - `/dashboard`
  - `/{page}`
  - `/api`
  - `/api/{endpoint}`
- [x] Menambahkan normalizer output agar link lama `home-admin.php?page=...` otomatis diarahkan ke route baru saat halaman dirender.
- [x] Merapikan sidebar menjadi lebih modern dan berbasis konfigurasi menu.
- [x] Menambahkan fondasi UI global:
  - `dist/css/simpeg-modern.css`
  - `dist/js/simpeg-app.js`
- [x] Menyamakan alur loading global untuk navigasi dan submit form.
- [x] Memperluas styling global agar form, modal, tabel, tab, dan tombol lebih konsisten di mobile dan desktop.
- [x] Menghapus `/app` dari URL route baru.
- [x] Memperbaiki bug loader global yang sempat error di halaman data pegawai.
- [x] Memperkuat halaman `form-view-data-pegawai` sebagai acuan template list/view data yang lebih modern.
- [x] Menambahkan approval perubahan data keluarga untuk role `user` melalui `tb_edit_pending`.
- [x] Menambahkan akses approval untuk Kabid Operasional/Kepala sesuai unit kerja.
- [x] Membuat bootstrap API internal pertama.
- [x] Menambahkan dokumentasi test API awal di `docs/API.md`.
- [x] Memperbaiki endpoint daftar pegawai aktif, non-jabatan, dan purna agar lebih aman untuk data yang belum punya jabatan atau sudah purna.
- [x] Menyesuaikan sidebar desktop agar default compact dan terbuka saat hover.
- [x] Memperbaiki tampilan search sidebar agar lebih rapi.
- [x] Mulai merapikan cluster halaman `pages/pegawai` agar template list, form, import, dan detail lebih konsisten.
- [x] Merapikan `profil-pegawai` agar user bisa ajukan perubahan biodata sendiri lewat approval.
- [x] Menambahkan approval `biodata_update` di modul otorisasi untuk role user pada profil pegawai.
- [x] Merapikan tampilan `form-ganti-foto` dan `form-ubah-id-peg` agar mengikuti theme modern yang sama.
- [x] Mengamankan modal gaji dan pangkat di profil pegawai supaya tidak error saat data tanggal/riwayat belum tersedia.
- [x] Memperbaiki responsif halaman yang sudah masuk fase perombakan:
  - `otorisasi-approval`
  - `profil-pegawai`
  - `view-detail-data-pegawai`
  - `form-view-data-user`
  - `preview-edit`
  - `notifikasi-user`
- [x] Menambahkan pola filter mobile buka/tutup pada halaman yang sudah dirapikan:
  - `form-view-data-pegawai`
  - `otorisasi-approval`
  - `form-view-data-user`
- [x] Menyesuaikan density tampilan halaman yang sudah diperbaiki agar spacing dan ukuran teks lebih nyaman di desktop, tablet, dan mobile.
- [x] Menyamakan modal pengajuan tambah/update keluarga role `user` di `profil-pegawai` dengan referensi form master keluarga:
  - dropdown pendidikan
  - dropdown pekerjaan dari `tb_master_pekerjaan`
  - dropdown status hubungan sesuai pasangan, anak, atau orang tua
- [x] Menyesuaikan form pengajuan biodata role `user` di `profil-pegawai` agar mengikuti pola `form-master-data-pegawai` tanpa upload foto:
  - dropdown agama, jenis kelamin, golongan darah, status nikah, status kepegawaian
  - field BPJS Ketenagakerjaan dan BPJS Kesehatan
  - tampilan profil ikut menampilkan nomor BPJS
- [ ] Merapikan halaman-halaman custom yang masih punya CSS inline besar agar benar-benar seragam dengan theme baru.
- [ ] Migrasi source code lama `home-admin.php?page=...` ke helper route baru secara bertahap di level file.
- [ ] Memecah query langsung di layer FE menjadi konsumsi REST API.
- [ ] Audit kompatibilitas penuh untuk upgrade ke PHP 8.

## Catatan API Internal

Folder `api/` sekarang sudah punya bootstrap awal:

- `api/index.php` sebagai router sederhana
- `api/controllers.php` untuk controller endpoint awal
- `api/helpers/response.php` untuk response JSON konsisten

Rencana pengembangan API:

- Menyediakan endpoint data pegawai untuk aplikasi internal lain.
- Menyediakan endpoint data keluarga pegawai.
- Menyediakan endpoint referensi seperti kantor, jabatan, pendidikan, diklat, dan sertifikasi.
- Menambahkan format response JSON yang konsisten.
- Menambahkan autentikasi API, misalnya token internal/API key atau session/token terpisah.
- Membatasi akses berdasarkan role dan kepemilikan data.

Endpoint yang sudah disiapkan saat ini:

```text
GET    /api
GET    /api/health
GET    /api/pegawai
GET    /api/pegawai/{id_peg}
GET    /api/pegawai/{id_peg}/keluarga
```

Dokumentasi test API awal ada di [docs/API.md](docs/API.md).

Contoh arah endpoint lanjutan:

```text
POST   /api/pegawai/{id_peg}/keluarga/anak
PUT    /api/pegawai/{id_peg}/keluarga/anak/{id_anak}
POST   /api/pegawai/{id_peg}/keluarga/pasangan
PUT    /api/pegawai/{id_peg}/keluarga/pasangan/{id_si}
POST   /api/pegawai/{id_peg}/keluarga/orang-tua
PUT    /api/pegawai/{id_peg}/keluarga/orang-tua/{id_ortu}
```

## Catatan Perombakan Role User

Kondisi saat ini:

- Role `user` diarahkan ke `home-admin.php?page=profil-pegawai`.
- Pada profil, user bisa melihat data sendiri.
- User bisa mengganti foto sendiri.
- User sudah bisa mengajukan perubahan biodata dan perubahan keluarga sendiri dari halaman profil.
- Pengajuan biodata dan keluarga masuk ke approval Kabid Operasional/Kepala cabang melalui `tb_edit_pending`.
- Edit langsung tanpa approval untuk biodata/riwayat strategis tetap dibatasi ke `admin` atau `kepala`.

Target perubahan:

- Role `user` bisa menambah dan mengubah data keluarga miliknya sendiri.
- Role `user` bisa mengajukan perubahan biodata miliknya sendiri.
- Data keluarga yang dimaksud:
  - suami/istri di `tb_suamiistri`
  - anak di `tb_anak`
  - orang tua di `tb_ortu`
- User tetap tidak boleh mengubah data pegawai lain.
- Perlu validasi bahwa `id_peg` pada request sama dengan `$_SESSION['id_pegawai']`.
- Untuk perubahan yang sensitif, bisa memakai mekanisme approval melalui `tb_edit_pending` dan modul `otorisasi`.

## Catatan Upgrade PHP 8

Repo ini masih memakai gaya PHP lama/prosedural. Sebelum menaikkan runtime ke PHP 8, bagian berikut perlu diaudit:

- Pemakaian `mysqli_*` tanpa prepared statement di beberapa file.
- Pemakaian `md5()` untuk password login.
- Query SQL yang masih menyisipkan input langsung ke string.
- Akses array tanpa pengecekan key yang dapat memunculkan warning di PHP 8.
- File duplikat/cadangan seperti `copy`, `ori`, atau file lama yang masih berada di repo.
- Dependency `phpoffice/phpspreadsheet` versi `^1.8` yang perlu diuji kompatibilitasnya dengan target PHP 8.
- Struktur API dan routing supaya tidak makin sulit dirawat.

Rekomendasi tahapan upgrade:

1. Rapikan konfigurasi koneksi dan base URL.
2. Tambahkan layer helper response dan request untuk API.
3. Ubah login dari `md5()` ke `password_hash()` dan `password_verify()` dengan migrasi bertahap.
4. Prioritaskan prepared statement untuk endpoint dan form yang menerima input user.
5. Jalankan audit kompatibilitas PHP 8 per modul.
6. Upgrade dependency Composer.
7. Uji modul login, pegawai, keluarga, import/export, dan laporan.

## Catatan Keamanan

Beberapa hal yang perlu diperhatikan sebelum aplikasi dipakai luas sebagai API internal:

- Jangan commit credential database production.
- Batasi akses folder `uploads/`, `db/`, dan file backup.
- API harus memiliki autentikasi dan pembatasan akses.
- Validasi file upload foto/dokumen harus ketat.
- Hindari query SQL langsung dari input user.
- Pastikan role `user` hanya dapat membaca/mengubah data miliknya sendiri.

## Roadmap Dekat

- [x] Membuat response helper JSON di `api/helpers/response.php`.
- [x] Membuat router sederhana di `api/index.php`.
- [x] Membuat controller API awal untuk data pegawai dan keluarga.
- [x] Membuka fitur tambah/edit keluarga untuk role `user` dengan alur approval.
- [x] Menentukan bahwa perubahan keluarga user masuk approval dulu.
- [x] Menambahkan fondasi route helper dan normalizer URL global.
- [x] Menambahkan dokumentasi test API awal.
- [ ] Menyelaraskan halaman lama yang masih punya inline CSS/JS sendiri dengan theme global.
- [ ] Memindahkan navigasi utama ke helper route baru langsung di source file secara menyeluruh.
- [ ] Menambahkan autentikasi untuk konsumsi API internal.
- [ ] Audit kompatibilitas PHP 8.
- [ ] Rapikan file duplikat dan file eksperimen lama.
