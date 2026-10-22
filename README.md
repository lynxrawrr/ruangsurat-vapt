# RuangSurat - Lab Pengantar Keamanan Informasi

Lab menyediakan dua profil aplikasi dari source yang sama: `vulnerable` sebagai baseline dengan kelemahan yang disengaja, dan `fixed` sebagai kandidat perbaikan. Seluruh akun, dokumen, dan lampiran awal menggunakan data fiktif.

## Tujuan dan pendekatan lab

- Memahami perbedaan autentikasi, otorisasi role, dan otorisasi kepemilikan dokumen.
- Menguji batas kepercayaan antara staf, administrator, konten pengguna, dan runtime server.
- Mempraktikkan recon, analisis, pembuktian, dan retest melalui browser serta HTTP.
- Membandingkan perilaku baseline dengan kandidat perbaikan tanpa menghilangkan fungsi aplikasi yang sah.
- Menyusun evidence, menjelaskan dampak yang terkonfirmasi, dan melakukan retest setelah remediasi.

Praktik utama menggunakan pendekatan **grey-box** melalui UI/HTTP dengan akun dan kebijakan akses yang disediakan. Peserta bekerja secara individu dan menunjukkan hasil langsung kepada instruktur. Review source dilakukan pada pembahasan akar masalah/remediasi; laporan historis juga mencatat pemeriksaan source/config sebagai kegiatan tambahan.

Laporan menggunakan PTES sebagai kerangka kegiatan, OWASP WSTG v4.2 untuk skenario pengujian, OWASP Top 10:2025 dan CWE untuk klasifikasi, serta CVSS v3.1 untuk severity teknis. Cakupan lab berfokus pada skenario yang didokumentasikan, bukan audit menyeluruh terhadap seluruh aplikasi atau infrastruktur.

## Fitur aplikasi

| Fitur                | Detail                                                                                                |
| -------------------- | ----------------------------------------------------------------------------------------------------- |
| Login dan logout     | Autentikasi email/password dengan sesi PHP; tersedia role staf dan administrator.                     |
| Dashboard pengajuan  | Daftar dokumen, ringkasan total, jumlah menunggu review, dan jumlah disetujui.                        |
| Pencarian dan filter | Pencarian pada daftar pengajuan serta filter Draft, Menunggu review, Disetujui, dan Ditolak.          |
| Pembuatan dokumen    | Judul, isi/keterangan, serta kategori Pengadaan, Surat tugas, Laporan, atau Operasional.              |
| Pengelolaan draft    | Pemilik dapat mengubah judul dan isi, menambahkan lampiran, serta menambahkan tautan sebelum dikirim. |
| Lampiran             | Unggah berkas maksimal 2 MB per berkas dan unduh melalui endpoint aplikasi.                           |
| Preview gambar       | Preview PNG/JPEG asli di halaman detail, buka ukuran penuh di tab baru, dan unduh gambar.             |
| Tautan referensi     | Tambahkan label dan alamat tautan pendukung, maksimal 10 tautan per dokumen.                          |
| Review administrator | Admin dapat membaca dokumen lintas staf dan menyetujui atau menolak pengajuan yang menunggu review.   |
| Direktori pengguna   | Daftar nama, email, dan role yang hanya tersedia bagi administrator.                                  |
| Tampilan responsif   | Antarmuka desktop dan mobile dengan indikator profil lab yang sedang digunakan.                       |

### Alur pengajuan

1. Staf login dan membuat pengajuan baru yang tersimpan sebagai `draft`.
2. Pemilik melengkapi isi, lampiran, dan tautan referensi.
3. Pemilik mengirim pengajuan sehingga status berubah menjadi `submitted` atau Menunggu review.
4. Administrator meninjau dokumen dan memberi keputusan `approved` atau `rejected`.
5. Staf melihat hasil keputusan melalui dashboard dan halaman detail.

Draft tidak dapat diubah atau ditambah lampiran/tautan setelah dikirim. Alur saat ini tidak menyediakan pengiriman ulang dokumen yang ditolak.

### Kebijakan akses yang diharapkan

| Tindakan                                       | Staf            | Administrator                     |
| ---------------------------------------------- | --------------- | --------------------------------- |
| Melihat daftar dan detail dokumen              | Dokumen sendiri | Semua dokumen                     |
| Membuat pengajuan                              | Ya              | Ya                                |
| Mengubah draft dan menambahkan lampiran/tautan | Draft sendiri   | Draft sendiri                     |
| Mengirim pengajuan                             | Draft sendiri   | Draft sendiri                     |
| Mengunduh lampiran dan melihat preview gambar  | Dokumen sendiri | Semua dokumen                     |
| Menyetujui/menolak pengajuan                   | Tidak           | Pengajuan berstatus `submitted` |
| Membuka direktori pengguna                     | Tidak           | Ya                                |

Tabel tersebut merupakan acuan untuk membandingkan perilaku aplikasi dengan kebijakan akses.

## Teknologi dan lingkungan

| Bagian            | Implementasi                                                                         |
| ----------------- | ------------------------------------------------------------------------------------ |
| Backend           | PHP; image Docker menggunakan PHP 8.4.26 dengan Apache.                              |
| Database          | SQLite melalui PDO; tabel `users`, `documents`, dan `attachments`.             |
| Frontend          | Template PHP, CSS, dan JavaScript native dengan `fetch()`; tanpa proses build npm. |
| Deployment        | Docker Compose dengan layanan `vulnerable`, `fixed`, dan `poc`.                |
| Pengujian API     | Python 3 dengan `unittest` dan pustaka standar.                                    |
| Pengujian browser | Python Playwright dan Chromium.                                                      |

Docker memisahkan database, sesi, dan storage lampiran kedua profil. Container berjalan sebagai `www-data`, memakai root filesystem read-only, membatasi capability, dan hanya memublikasikan port pada loopback host.

## Menjalankan lab dengan Docke

Prasyarat: Docker Engine/Docker Desktop dengan Docker Compose, browser, serta port host 8080, 8081, dan 8090 yang tersedia. Jalankan perintah dari direktori root project. Build pertama memerlukan akses untuk mengunduh base image.

```bash
docker compose --profile vulnerable --profile fixed --profile poc up --build -d
```

| Layanan        | URL                   | Kegunaan                                          |
| -------------- | --------------------- | ------------------------------------------------- |
| `vulnerable` | http://127.0.0.1:8080 | Baseline dengan kelemahan yang disengaja.         |
| `fixed`      | http://127.0.0.1:8081 | Kandidat perbaikan untuk perbandingan dan retest. |
| `poc`        | http://127.0.0.1:8090 | Halaman pengujian framing dan CORS data publik.   |

Gunakan `127.0.0.1` secara konsisten untuk aplikasi dan halaman PoC. Profil `vulnerable` berisi kelemahan yang disengaja; gunakan pada lingkungan lokal dengan data uji dan pertahankan binding loopback yang tersedia di Compose.

Untuk memeriksa status layanan dan log aplikasi:

```bash
docker compose --profile vulnerable --profile fixed --profile poc ps
docker compose logs --tail=100 vulnerable fixed
```

Endpoint `GET /health` tersedia pada kedua profil untuk pemeriksaan koneksi database.

### Akun dan data awal

| Akun   | Nama         | Email               | Password pengujian    |
| ------ | ------------ | ------------------- | --------------------- |
| Staf A | Alya Pratama | staf.a@example.test | RuangSurat!2026-A     |
| Staf B | Bima Saputra | staf.b@example.test | RuangSurat!2026-B     |
| Admin  | Nadia Putri  | admin@example.test  | RuangSurat!2026-Admin |

Ketiga akun tersedia pada kedua profil. Inisialisasi pertama membuat empat dokumen fiktif dengan status draft, submitted, dan approved, serta satu lampiran TXT. Database yang sudah terisi dipertahankan saat layanan dijalankan ulang.

### Alternatif tanpa Docker

Gunakan PHP yang mendukung sintaks aplikasi beserta PDO SQLite, Fileinfo, dan dukungan pemeriksaan gambar. Image Docker menjadi acuan runtime jika ada perbedaan versi PHP lokal.

```bash
./scripts/dev.sh vulnerable
./scripts/dev.sh fixed
```

Jalankan kedua perintah pada terminal terpisah. Script menginisialisasi data dan menjalankan server bawaan PHP pada port 8080 atau 8081. Untuk menjalankan halaman PoC, buka terminal tambahan:

```bash
php -S 127.0.0.1:8090 -t poc
```

Database, sesi, dan lampiran privat berada di `.runtime/<profil>/`. Direktori upload publik `app/public/uploads/` tetap dipakai bersama oleh source lokal; gunakan Docker untuk isolasi penuh kedua profil. Jangan menjalankan layanan lokal dan Docker pada port yang sama secara bersamaan.

## Pengujian

Ikuti [Hands-on VAPT](PANDUAN_LAB.md) dari recon sampai retesting dan penyusunan laporan ringkas. Panduan mencakup pemetaan dan perhitungan CVSS v3.1, penjelasan dampak, prioritas perbaikan, dan kunci jawaban.

Mulai dari bagian eksplorasi. Langkah penyelesaian, akar masalah, perbaikan, dan retest tersedia dalam **Pembahasan solusi** yang dapat dilipat; buka setelah mencoba. Kunci jawaban penilaian dan referensi teknis endpoint serta pengujian otomatis tersedia di bagian akhir panduan.

## Reset dan menghentikan lab

Untuk mengembalikan data awal profil Docker yang sedang berjalan:

```bash
./scripts/reset-lab.sh vulnerable
./scripts/reset-lab.sh fixed
```

Reset menghapus database, sesi, dan lampiran profil terkait, lalu membuat ulang data awal. Evidence tidak dihapus; login ulang setelah reset. Script reset ini menggunakan Docker Compose.

Untuk menghentikan layanan:

```bash
docker compose --profile vulnerable --profile fixed --profile poc down
```

Perintah `down` tersebut mempertahankan named volume sehingga data aplikasi tersedia saat layanan dijalankan kembali.

## Laporan final

Laporan versi 1.1 mendokumentasikan assessment staging sebelum production, bukti temuan, dampak, rekomendasi, dan retest kandidat perbaikan:

- [Word](laporan/Laporan_VAPT_Staging_RuangSurat_Kevin_McLoud_v1.1.docx)
- [PDF](laporan/Laporan_VAPT_Staging_RuangSurat_Kevin_McLoud_v1.1.pdf)
- [Markdown](laporan/Laporan_VAPT_Staging_RuangSurat_Kevin_McLoud_v1.1.md)

Laporan berisi hasil pengujian dan dipakai sebagai bahan pembahasan setelah fase eksplorasi. Status perbaikan berlaku untuk profil dan skenario retest yang didokumentasikan.

Gambar laporan Markdown mengarah ke salinan evidence di `archive/`. Word dan PDF sudah menyematkan seluruh gambar. Arsip tersedia pada workspace lokal jika disertakan, tetapi diabaikan Git dan build Docker sehingga gambar Markdown dapat tidak tampil pada clone repository tanpa arsip tersebut.

## Struktur project

```text
app/
  bootstrap.php         # Konfigurasi mode, database, seed, dan helper
  console.php           # Perintah CLI seed/reset
  public/
    index.php           # Routing halaman/API, sesi, dan kontrol akses
    router.php          # Router untuk server PHP lokal
    assets/             # JavaScript dan CSS
  views/page.php        # Template antarmuka aplikasi
docker/                 # Dockerfile, Apache, PHP, dan entrypoint
compose.yaml            # Layanan, port, profil, dan volume Docker
PANDUAN_LAB.md           # Hands-on, laporan, CVSS, dan pembahasan solusi
poc/                    # Materi demonstrasi browser dan berkas uji
scripts/                # Menjalankan lab, reset, dan pengumpulan evidence
tests/                  # Suite API dan browser
laporan/                # Laporan final Word, PDF, dan Markdown
archive/                # Evidence dan dokumen pendukung lokal; diabaikan Git
.runtime/               # Data dari dev.sh; dibuat saat dijalankan
```

## Batas cakupan

Target pengujian adalah aplikasi lokal, akun uji, serta tiga layanan yang disebutkan di atas. Production, layanan internet lain, host Windows/WSL, daemon Docker, container escape, dan gangguan layanan berada di luar cakupan assessment. Kesimpulan dibatasi pada perilaku yang benar-benar diuji, tanpa mengklaim pengambilalihan host atau dampak lanjutan yang belum dibuktikan.
