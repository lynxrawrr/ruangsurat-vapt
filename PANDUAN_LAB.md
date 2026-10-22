# Hands-on VAPT — RuangSurat

Panduan ini mencakup recon, pengujian melalui browser dan HTTP, pembuktian temuan, retest, serta penyusunan laporan. Jalankan langkahnya pada lingkungan lokal dan simpan bukti hasil pengujian.

Mulai dari eksplorasi aplikasi, lalu buka **Pembahasan solusi** setelah mencoba. Pendekatan pengujian adalah **grey-box** karena akun dan kebijakan akses sudah disediakan. Panduan penilaian CVSS, dampak, dan prioritas perbaikan tersedia sebelum pembahasan solusi; kunci jawabannya tersedia setelah langkah eksploitasi.

## 0. Siapkan aplikasi dan tool

Siapkan Docker dengan Docker Compose, lalu jalankan dari direktori root project:

```bash
docker compose --profile vulnerable --profile fixed --profile poc up --build -d
```

Pastikan port `8080`, `8081`, dan `8090` tersedia. Petunjuk lingkungan dan alternatif tanpa Docker ada di [README](README.md). Mulai dari eksplorasi manual, lalu gunakan source, pembahasan solusi, dan tes otomatis untuk memeriksa hasilnya.

Siapkan terminal, browser, dan Burp Suite Community. Nmap dan Nuclei dipakai jika sudah tersedia.

Target awal:

```text
http://127.0.0.1:8080
```

Gunakan hostname `127.0.0.1` secara konsisten. Target retest adalah `http://127.0.0.1:8081`; halaman PoC untuk observasi browser tersedia pada `http://127.0.0.1:8090`.

### Akun pengujian

| Role          | Email                   | Password lab              |
| ------------- | ----------------------- | ------------------------- |
| Staf A        | `staf.a@example.test` | `RuangSurat!2026-A`     |
| Staf B        | `staf.b@example.test` | `RuangSurat!2026-B`     |
| Administrator | `admin@example.test`  | `RuangSurat!2026-Admin` |

Kamu boleh menggunakan akun secara bergantian: selesaikan tindakan satu akun, logout, lalu login akun berikutnya. Untuk pengamatan bersamaan, gunakan profil browser terpisah; tab biasa dalam profil yang sama berbagi cookie. Saat berpindah akun, ambil request baru dari sesi yang aktif. Request dengan sesi yang sudah logout tidak dapat menjadi pembanding yang valid.

### Aturan aplikasi

- Pengunjung tanpa login tidak boleh memperoleh dokumen privat atau memakai fungsi privat.
- Staf boleh membuat pengajuan, membaca dokumen sendiri, dan mengelola draft sendiri.
- Informasi, lampiran, dan tautan dapat ditambahkan pada draft milik sendiri.
- Setelah dikirim, draft tidak dapat diubah dan menunggu keputusan admin.
- Admin boleh membaca dokumen lintas staf, membuka direktori pengguna, serta memberi keputusan pada pengajuan yang menunggu review.
- Admin tetap mengikuti aturan kepemilikan saat mengelola draft.
- Lampiran privat mengikuti hak akses dokumennya; konten pengguna harus diproses sesuai fungsi bisnis.

Uji hanya aplikasi dan data fiktif milikmu pada akun lab. Gunakan penanda unik untuk membedakan pengajuan. Hindari target internet, port lain, DoS, shell/perintah OS, penghapusan massal, dan reset lingkungan bersama. Jika diperlukan pembuktian eksekusi, gunakan marker lokal tanpa mengambil file, mengirim data, atau mengubah objek lain.

### Hubungkan browser dengan Burp

1. Buka Burp Suite Community.
2. Atur proxy listener Burp ke `127.0.0.1:8082` melalui pengaturan **Proxy listeners**. Port aplikasi `8080` sudah dipakai oleh lab, sehingga listener Burp perlu port berbeda.
3. Buka **Proxy → Intercept**, lalu pilih **Open browser**.
4. Pastikan **Intercept off** agar navigasi berjalan lancar.
5. Di browser Burp, buka alamat aplikasi.
6. Buka **Proxy → HTTP history** dan pastikan request aplikasi terlihat. Batasi tampilan ke host serta port aplikasi agar request mudah ditemukan.

Browser bawaan Burp sudah dikonfigurasi untuk menggunakan proxy Burp. HTTP history tetap merekam request saat interception dimatikan. Rujukan: [browser Burp](https://portswigger.net/burp/documentation/desktop/tools/burps-browser) dan [HTTP history saat Intercept off](https://portswigger.net/burp/documentation/desktop/getting-started/intercepting-http-traffic).

## 1. Recon — lihat apa yang dihadapi

### Periksa respons HTTP

Di terminal, jalankan:

```bash
curl -i http://127.0.0.1:8080/
```

Lihat status respons, header, cookie, dan tujuan redirect. Ikuti tujuan yang masih berada pada aplikasi lab melalui browser. Amati informasi apa yang benar-benar diberikan server; jangan menganggap informasi versi sebagai bukti adanya celah.

### Periksa layanan target jika Nmap tersedia

```bash
nmap -sT -sV -p 8080 127.0.0.1
```

Bandingkan pengenalan layanan oleh Nmap dengan respons HTTP tadi. Pengujian ini hanya untuk port aplikasi yang masuk scope.

### Deteksi teknologi jika Nuclei tersedia

```bash
nuclei -u http://127.0.0.1:8080 -tags tech -rl 5 -c 2
```

Lihat hasil di terminal. Opsi tersebut memilih template bertag teknologi dengan batas 5 request per detik dan 2 template paralel. Ini recon aktif tanpa sesi login. Hasil kosong tidak membuktikan aplikasi aman. Rujukan opsi: [Nuclei](https://github.com/projectdiscovery/nuclei#usage).

Lanjutkan ke penggunaan aplikasi; hasil scanner tidak menentukan seluruh pengujian berikutnya.

## 2. Jalankan fitur normal sambil melihat request

1. Login sebagai Staf A melalui browser Burp.
2. Buat pengajuan dengan judul berpenanda unik, misalnya `UJI-ANDA-NORMAL`.
3. Buka detail pengajuan dan coba fitur yang tersedia menggunakan konten biasa, berkas TXT/gambar buatan sendiri, serta tautan HTTP/HTTPS biasa seperti `https://example.test/referensi`. URL ini cukup disimpan sebagai data uji dan tidak perlu dibuka ke internet.
4. Ubah informasi draft, lalu kirim pengajuan.
5. Logout dan login sebagai admin. Buka pengajuan tadi dan jalankan proses review.
6. Login sebagai Staf B, buat pengajuan milik akun tersebut, dan jelajahi fitur normalnya.
7. Pada **HTTP history**, buka request yang muncul dari setiap tindakan.

Perhatikan method, path, input, cookie, token, body respons, serta perubahan tampilan. Temukan sendiri endpoint dari tindakan yang kamu lakukan. Temukan endpoint dari HTTP history sebelum membuka referensi pada pembahasan solusi.

## 3. Kirim ulang satu request normal

1. Pada **HTTP history**, pilih request dari fitur yang baru kamu gunakan dengan sesi yang masih aktif.
2. Klik kanan request dan pilih **Send to Repeater**.
3. Buka tab **Repeater** dan tekan **Send** tanpa mengubah request.
4. Bandingkan respons dengan perilaku di browser.
5. Jika request mengubah data, gunakan objek uji dan pahami bahwa mengirim ulang dapat mengulangi tindakan.

Repeater memungkinkan kamu mengubah dan mengirim ulang request secara manual. Rujukan: [penggunaan Repeater](https://portswigger.net/burp/documentation/desktop/tools/repeater/http-messages).

Jika request normal gagal, periksa sesi, token, objek, dan status proses terlebih dahulu. Ambil request baru dari browser bila diperlukan. Pastikan baseline bekerja sebelum mencoba variasi.

## 4. Pilih sasaran uji — threat modeling singkat

Di aplikasi yang sudah terbuka, pilih satu fitur dan tentukan dalam pikiran:

- Apa yang perlu dilindungi pada fitur itu?
- Siapa pelakunya dan apa akses awalnya?
- Apa yang seharusnya diizinkan atau ditolak menurut aturan aplikasi?
- Bagian request atau alur mana yang dapat dikendalikan pelaku?

Lalu pilih satu dugaan untuk diuji. Tentukan hasil yang seharusnya dan bukti apa yang akan menunjukkan pelanggaran sebelum menekan **Send**. Memegang akun admin untuk pembanding tidak berarti setiap pelaku diasumsikan sudah punya hak admin.

Kegiatan ini menghubungkan aset, proses, aktor, dan kemampuannya, sesuai [pendekatan threat modeling PTES](https://pentest-standard.readthedocs.io/en/latest/threat_modeling.html). Tidak perlu membuat tabel atau dokumen model.

## 5. Uji perilaku aplikasi melalui request

Ulangi pola ini pada fitur yang kamu pilih:

1. Jalankan tindakan normal dengan akun yang sesuai.
2. Kirim request-nya ke Repeater dan pastikan baseline bekerja.
3. Ubah **satu hal** yang berkaitan dengan dugaanmu: input, referensi objek, identitas sesi, atau urutan tindakan.
4. Tekan **Send** dan lihat responsnya.
5. Periksa hasil di browser atau objek uji bila pengaruhnya tidak terlihat pada respons saja.
6. Kembalikan kondisi normal, lalu bandingkan lagi.

Jelajahi area berikut berdasarkan fitur yang sudah kamu temukan. Tidak semua area harus menghasilkan temuan.

### Identitas dan akses

Gunakan akun lab bergantian untuk menjalankan tindakan terhadap objek uji yang kamu buat sendiri di masing-masing akun. Bandingkan hak staf, admin, dan kondisi tanpa login. Saat membandingkan identitas, gunakan cookie serta token yang sesuai dengan sesi tersebut.

### Input dan cara aplikasi memakainya

Coba variasi nilai pada fitur yang menerima input. Amati apakah nilai ditolak, dinormalisasi, disimpan, ditampilkan kembali, atau diproses di tempat lain. Jika melibatkan role lain, buka hasilnya melalui sesi role tersebut agar konteks pengamatan benar.

### Aturan proses

Gunakan pengajuan uji untuk membandingkan tindakan saat status proses berubah. Coba urutan tindakan yang berbeda pada data uji, lalu periksa apakah aturan aplikasi tetap berlaku.

### Sesi dan perubahan data

Bandingkan request normal dengan kondisi sesi atau token yang berubah. Periksa ulang setelah logout. Penolakan karena tidak login berbeda dari pembatasan suatu tindakan bagi pengguna yang sudah login.

Jika tidak ada penyimpangan, lanjutkan ke dugaan atau fitur lain. Jika hasil berbeda dari kebijakan, pertahankan tab request terkait dan lanjutkan validasi. Respons sukses saja belum membuktikan pelanggaran; respons error juga belum membuktikan kontrol yang diuji bekerja.

## 6. Buktikan hasil yang menyimpang

1. Mulai lagi dari kondisi awal yang jelas: akun, objek uji, dan status proses yang benar.
2. Ulangi tindakan yang menghasilkan penyimpangan.
3. Periksa hasil yang membuktikan aturan dilanggar, bukan sekadar request diterima.
4. Jalankan tindakan pembanding yang sah agar perbedaannya terlihat.
5. Jika melibatkan role lain, gunakan sesi akun lab yang sesuai dan pastikan identitasnya terlihat di aplikasi.
6. Simpan request, respons, dan screenshot hasil di browser sebagai bukti laporan. Redaksi cookie dan token aktif sebelum menyertakannya.

Berhenti setelah kemampuan minimum terbukti. Jika diperlukan marker, gunakan penanda lokal yang unik. Jangan memperluas pembuktian ke pengambilan data, perubahan tambahan, atau target lain.

Jika penyimpangan belum dapat direproduksi atau penyebabnya belum jelas, kembali ke langkah sebelumnya. Tidak ada jumlah temuan yang harus kamu capai.

## 7. Evaluasi dampak dan perbaikan

Dengan hasil masih terbuka di layar, catat:

- Akses atau kemampuan yang benar-benar berhasil diperoleh.
- Aset atau proses yang terkena dampak.
- Batas yang belum berhasil dilewati atau belum diuji.

Ini bagian evaluasi dampak pada post-exploitation. Cukup gunakan hasil yang sudah diperoleh; tidak perlu menambahkan serangan lanjutan.

Tentukan aturan mana yang gagal dan kontrol apa yang seharusnya menegakkannya. Buka pembahasan solusi dan source terkait untuk memeriksa akar masalah. Kandidat perbaikan sudah tersedia melalui profil `fixed`.

Catat juga pengujian yang tidak menemukan pelanggaran. Bedakan hasil terkonfirmasi, dugaan yang belum terbukti, dan area yang belum diuji.

## 8. Retesting — jalankan lagi pada kandidat perbaikan

Gunakan kandidat perbaikan pada `http://127.0.0.1:8081`:

1. Buka target tersebut dan login ulang. Gunakan sesi serta token dari target retest.
2. Buat data uji baru dan jalankan fitur normal yang berkaitan dengan hasil sebelumnya.
3. Ambil request baru melalui HTTP history dan kirim ke Repeater.
4. Ulangi perubahan atau urutan tindakan yang sebelumnya membuktikan pelanggaran, dengan kemampuan awal pelaku yang setara.
5. Sesuaikan referensi objek dengan data baru. Jangan memakai ID lama secara membabi buta.
6. Periksa apakah pelanggaran masih dapat terjadi.
7. Jalankan tindakan sah dengan akun yang berhak untuk memastikan fungsi normal tetap bekerja.
8. Simpan perbandingan hasil awal dan hasil retest untuk laporan.

Kegagalan karena cookie salah, token kedaluwarsa, data tidak ada, atau layanan mati belum membuktikan perbaikan. Pastikan prasyarat uji valid terlebih dahulu.

Jika pelanggaran masih terjadi, catat sebagai **masih terbuka**. Jika sudah tertahan dan fungsi normal bekerja, perbaikan terverifikasi untuk skenario tersebut. Jika kondisi pengujian belum memadai, catat sebagai **belum dapat diverifikasi**.

## 9. Susun laporan hasil pengujian

Buat laporan ringkas dalam Word atau PDF. Gunakan hasil dan screenshot pengujianmu sendiri. Susunan berikut sudah cukup; bukti diletakkan langsung pada temuan terkait sehingga tidak perlu bagian lampiran terpisah.

1. **Identitas dan scope:** nama penyusun, tanggal, target, profil aplikasi, akun/role, serta batas pengujian.
2. **Metode dan tools:** kegiatan yang benar-benar dilakukan, tool yang digunakan, dan keterbatasannya.
3. **Ringkasan temuan:** ID, judul, skor dan severity CVSS, prioritas perbaikan, serta status retest.
4. **Detail tiap temuan:** gunakan format di bawah.
5. **Hasil retest:** bandingkan pengujian pada `vulnerable` dan `fixed`, termasuk tindakan sah yang tetap harus bekerja.
6. **Kesimpulan dan referensi:** jelaskan perbaikan yang diprioritaskan, batas kesimpulan, dan sumber metode penilaian.

### Format detail temuan

| Bagian                     | Isi yang perlu ditulis                                                                                             |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| ID dan judul               | Penanda unik dan masalah yang ditemukan.                                                                           |
| Target dan prasyarat       | Endpoint, profil, role pelaku, pemilik objek, dan status dokumen.                                                  |
| Langkah reproduksi         | Urutan tindakan yang dapat diulang dengan objek uji yang jelas.                                                    |
| Expected dan actual result | Aturan yang seharusnya berlaku dibandingkan hasil aktual.                                                          |
| Bukti                      | Request/respons atau screenshot dengan caption: akun, objek, tindakan, dan hasil. Redaksi cookie/token aktif.      |
| Dampak dan batas bukti     | Apa yang terbukti; pisahkan dari kemampuan lanjutan yang dinilai atau belum diuji.                                 |
| CVSS v3.1                  | Vector lengkap, alasan delapan metrik, Base Score, dan severity teknis.                                            |
| Prioritas perbaikan        | Urutan penanganan dan alasannya berdasarkan severity teknis, dampak, sensitivitas data, dan kemudahan eksploitasi. |
| Perbaikan                  | Kontrol yang menangani akar masalah.                                                                               |
| Retest                     | Target, hasil pengulangan, hasil tindakan sah, bukti, dan status perbaikan.                                        |

Jangan menuliskan temuan sebagai terkonfirmasi jika belum berhasil dibuktikan. ID dokumen, penanda, dan waktu pengujian boleh berbeda dari contoh jawaban. Tulis kondisi yang benar-benar kamu amati.

## 10. Petakan temuan ke CVSS v3.1

Gunakan **CVSS v3.1 Base Score** agar konsisten dengan laporan referensi project. Ini pilihan versi untuk panduan ini, bukan pernyataan bahwa v3.1 adalah versi terbaru. Base Score menilai severity teknis. Dampak bisnis dan alasan prioritas perbaikan dijelaskan secara naratif pada bagian 11. Rujukan: [FIRST CVSS v3.1 User Guide](https://www.first.org/cvss/v3.1/user-guide).

### 10.1. Pilih nilai dari skenario yang dibuktikan

Mulai dari kemampuan awal pelaku, cara memicu kelemahan, komponen yang terkena dampak, dan perubahan kemampuan sesudah eksploitasi. Nama celah seperti “XSS” atau “IDOR” saja belum menentukan skor.

| Metrik                              | Pertanyaan untuk menentukan nilai            | Pilihan                                                                          |
| ----------------------------------- | -------------------------------------------- | -------------------------------------------------------------------------------- |
| **AV — Attack Vector**       | Melalui jalur apa kelemahan dimanfaatkan?    | `N` jaringan, `A` jaringan berdekatan, `L` akses lokal, `P` akses fisik. |
| **AC — Attack Complexity**   | Ada kondisi khusus di luar kendali pelaku?   | `L` rendah, `H` tinggi.                                                      |
| **PR — Privileges Required** | Hak apa yang diperlukan sebelum eksploitasi? | `N` tanpa hak, `L` hak rendah, `H` hak tinggi.                             |
| **UI — User Interaction**    | Perlu tindakan pengguna lain?                | `N` tidak, `R` perlu.                                                        |
| **S — Scope**                | Dampak melintasi otoritas keamanan komponen? | `U` tetap, `C` berubah.                                                      |
| **C — Confidentiality**      | Seberapa besar kerahasiaan terpengaruh?      | `N` tidak, `L` rendah, `H` tinggi.                                         |
| **I — Integrity**            | Seberapa besar integritas terpengaruh?       | `N` tidak, `L` rendah, `H` tinggi.                                         |
| **A — Availability**         | Seberapa besar ketersediaan terpengaruh?     | `N` tidak, `L` rendah, `H` tinggi.                                         |

Hal yang perlu diperhatikan pada RuangSurat:

- HTTP ke `127.0.0.1` tetap menggunakan `AV:N` pada skenario ini. Lokasi aplikasi di localhost tidak berarti pelaku harus memiliki akses lokal untuk memicu kelemahan.
- Akun staf yang dibutuhkan pelaku dinilai `PR:L`. Admin sebagai pihak yang membuka tautan tidak mengubah hak awal pelaku menjadi `PR:H`.
- `UI:R` berarti perlu tindakan pengguna lain. Pelaku membuka URL unggahannya sendiri tidak dihitung sebagai interaksi korban.
- Scope bukan perpindahan akun atau port. Akses antarstaf dalam aplikasi yang sama dapat tetap `S:U`; script dari aplikasi yang berjalan dalam otoritas browser korban dapat dinilai `S:C`.
- Nilai C/I/A mengikuti dampak teknis yang dapat dipertanggungjawabkan. Jelaskan asumsi kemampuan kode dan pisahkan dari tindakan yang benar-benar diuji. Tidak perlu merusak data untuk menaikkan skor.

### 10.2. Bentuk vector dan periksa skor

1. Tulis delapan metrik beserta alasannya.
2. Susun vector dengan format `CVSS:3.1/AV:…/AC:…/PR:…/UI:…/S:…/C:…/I:…/A:…`.
3. Masukkan pilihan ke [kalkulator resmi FIRST v3.1](https://www.first.org/cvss/calculator/3.1).
4. Salin vector, Base Score, dan severity ke laporan. Pastikan keluaran kalkulator sesuai pilihan metrikmu.

| Base Score | Severity teknis |
| ---------- | --------------- |
| 0.0        | None            |
| 0.1–3.9   | Low             |
| 4.0–6.9   | Medium          |
| 7.0–8.9   | High            |
| 9.0–10.0  | Critical        |

### 10.3. Cara menghitung Base Score

Bobot berikut ditetapkan dalam [standar resmi FIRST CVSS v3.1, bagian 7.4, Tabel 16](https://www.first.org/cvss/v3.1/specification-document#7-4-Metric-Values). Pilih kategori metrik berdasarkan skenario pengujian, lalu gunakan bobot standarnya. Bobot `0.85` tidak berarti peluang serangan 85%.

Konstanta yang dipakai oleh tiga contoh RuangSurat:

| Nilai metrik                                | Bobot           |
| ------------------------------------------- | --------------- |
| `AV:N`                                    | 0.85            |
| `AC:L`                                    | 0.77            |
| `PR:L` saat `S:U`                       | 0.62            |
| `PR:L` saat `S:C`                       | 0.68            |
| `UI:N` / `UI:R`                         | 0.85 / 0.62     |
| C, I, atau A bernilai `N` / `L` / `H` | 0 / 0.22 / 0.56 |

Gunakan bobot tersebut dalam rumus berikut. Huruf C/I/A di dalam rumus adalah bobot metrik, bukan huruf pilihannya.

```text
ISS = 1 − ((1 − C) × (1 − I) × (1 − A))
Exploitability = 8.22 × AV × AC × PR × UI

Jika S:U:
  Impact = 6.42 × ISS
  Base Score = Roundup(min(Impact + Exploitability, 10))

Jika S:C:
  Impact = 7.52 × (ISS − 0.029) − 3.25 × (ISS − 0.02)^15
  Base Score = Roundup(min(1.08 × (Impact + Exploitability), 10))

Jika Impact ≤ 0, Base Score = 0.
```

`Roundup` membulatkan **ke atas ke satu angka desimal**, bukan ke angka terdekat. Contoh: `4.24765473 → 4.3`, sedangkan `4.0 → 4.0`. Pertahankan presisi selama perhitungan; lakukan Roundup pada hasil akhir. Rumus dan bobot lengkap: [FIRST CVSS v3.1, bagian 7](https://www.first.org/cvss/v3.1/specification-document#7-CVSS-v3-1-Equations).

## 11. Jelaskan dampak dan tentukan prioritas perbaikan

Gunakan CVSS untuk severity teknis. Jelaskan konsekuensi terhadap data dan proses bisnis dengan kalimat yang didukung hasil pengujian. Prioritas merupakan urutan penanganan, bukan nilai yang dihitung dengan rumus tambahan.

### 11.1. Jelaskan dampak secara naratif

Untuk setiap temuan, tulis:

- **Aset atau proses:** dokumen, konteks browser admin, atau runtime aplikasi yang terpengaruh.
- **Kemampuan yang terbukti:** data yang terbaca atau kode yang berhasil dijalankan.
- **Konsekuensi bisnis:** pengaruhnya terhadap kerahasiaan dokumen atau keandalan proses pengajuan dan review.
- **Batas bukti:** bedakan hasil pengujian dari kemampuan lanjutan yang dinilai atau belum diuji.

Contoh cara menulisnya: “Akun staf dapat membaca isi dokumen pengguna lain. Hal ini melanggar kerahasiaan pengajuan. Pengujian belum membuktikan perubahan dokumen atau gangguan layanan.” Gunakan uraian ini hanya jika sesuai hasil pengujianmu.

### 11.2. Tentukan urutan penanganan

1. Mulai dari severity dan kemampuan teknis yang ditunjukkan CVSS.
2. Pertimbangkan sensitivitas data, hak awal pelaku, kebutuhan tindakan korban, serta dampaknya terhadap proses bisnis.
3. Tetapkan urutan seperti **P1**, **P2**, dan **P3**, lalu tulis alasan singkatnya. P1 berarti ditangani lebih dahulu, bukan kategori CVSS baru.

Temuan dengan CVSS lebih rendah dapat didahulukan apabila konteks data atau proses mendukung keputusan tersebut. Jelaskan alasannya; skor CVSS tetap mengikuti metrik teknis yang dipilih. Gunakan kunci jawaban sebagai contoh, lalu sesuaikan prioritas dengan bukti dan konteks pengujianmu.

## Selesai pengujian dan laporan

Logout dari akun lab dan tutup sesi yang tidak lagi digunakan. Simpan evidence sebelum membersihkan data uji pada lingkungan lokal yang kamu kelola. Setelah recon, pembuktian, dan retest selesai, susun hasilnya menjadi laporan sesuai bagian 9.

PTES menjadi alur kegiatan pengujian. Bagian reporting diwujudkan melalui laporan yang menjelaskan temuan, bukti, severity teknis, dampak, prioritas perbaikan, dan hasil retest.

## Pembahasan solusi — buka setelah mencoba

<details>
<summary>Tampilkan langkah eksploitasi, akar masalah, perbaikan, dan retest</summary>

Bagian ini berisi langkah pembuktian, akar masalah, dan retest. Gunakan aplikasi awal pada `http://127.0.0.1:8080` dan kandidat perbaikan pada `http://127.0.0.1:8081`. Ketiga akun tersedia di kedua lingkungan. Gunakan ID dokumen dari data uji yang kamu buat; ID kedua lingkungan bisa berbeda. Perbaikan sudah disediakan melalui profil `fixed`, sehingga kamu cukup mengulang pengujian tanpa mengedit kode sendiri.

### 1. IDOR — membaca dokumen staf lain

**Lakukan pada aplikasi awal:**

1. Login sebagai Staf B dan buat draft berjudul `ID-ANDA-MILIK-BIMA`, dengan isi yang mudah dikenali. Ambil ID-nya dari URL halaman detail.
2. Logout, lalu login sebagai Staf A. Buat dan buka dokumen milik Staf A sebagai pembanding yang sah.
3. Di address bar, buka `/documents/{id_dokumen_bima}` memakai ID dari langkah pertama.
4. Periksa judul, isi, pemilik dokumen, serta identitas Staf A pada sidebar. Dokumen Bima dapat dibaca dalam sesi Alya meskipun kebijakan melarangnya.
5. Di Burp, kirim request `GET /api/documents/{id_dokumen_alya}` dengan sesi Alya yang aktif ke Repeater. Ubah hanya ID menjadi ID dokumen Bima, lalu tekan **Send**.
6. Respons yang menunjukkan isi dan pemilik dokumen Bima membuktikan akses baca lintas pemilik. HTTP 200 saja belum cukup tanpa memeriksa isinya.

**Akar masalah:** fungsi `document()` pada [backend](app/public/index.php) memiliki pengecualian pemeriksaan kepemilikan saat pembacaan pada profil awal. Filter daftar dashboard tidak melindungi akses langsung ke detail. Kelemahan ini dibatasi pada pembacaan detail; perubahan dokumen dan endpoint unduhan lampiran tetap memeriksa akses.

**Cara memperbaiki:** periksa pemilik atau role admin pada setiap pembacaan objek, sebelum data dikembalikan. Hapus pengecualian baca yang melewati pemeriksaan tersebut. Inti pemeriksaannya:

```php
if (!$d || ($user['role'] !== 'admin' && (int)$d['owner_id'] !== (int)$user['id'])) {
    fail('Dokumen tidak ditemukan', 404);
}
```

Cuplikan ini menjelaskan kontrol yang sudah tersedia pada profil `fixed`; tidak perlu mengubah kode untuk menjalankan retest. Pastikan pemeriksaan berlaku pada halaman, API, dan akses lampiran.

**Retest pada kandidat perbaikan:**

1. Buat dokumen baru sebagai Staf B pada port `8081`.
2. Login sebagai Staf A pada target yang sama dan ulangi akses halaman/API menggunakan ID baru itu.
3. Akses silang harus ditolak dengan 404 tanpa isi dokumen.
4. Login sebagai pemilik dan admin; keduanya harus tetap dapat membaca dokumen tersebut.

### 2. Stored XSS — tautan staf aktif dalam browser admin

**Lakukan pada aplikasi awal:**

1. Login sebagai Staf A pada port `8080` dan buat draft baru.
2. Pada **Tautan referensi**, gunakan label `Referensi uji` dan masukkan alamat berikut:

```text
javascript:alert('RUANGSURAT_XSS')
```

3. Tambahkan tautan lalu kirim pengajuan untuk review.
4. Logout dan login sebagai admin. Buka dokumen yang baru dibuat staf, kemudian klik `Referensi uji`.
5. Dialog `RUANGSURAT_XSS` membuktikan script berjalan pada konteks browser admin setelah admin membuka tautan staf.

**Akar masalah:** profil awal menyimpan skema URL aktif dan merendernya sebagai `href`. Encoding HTML saja tidak membatasi skema URL. Ini membutuhkan interaksi pengguna yang membuka tautan; penyimpanan payload saja belum membuktikan eksekusi lintas akun.

**Cara memperbaiki:** gunakan validasi URL di server dengan allowlist HTTP/HTTPS, cek kembali saat render agar data lama tidak menjadi tautan aktif, dan tambahkan CSP sebagai lapisan tambahan. Implementasinya sudah ada pada `safe_url()` di [bootstrap](app/bootstrap.php), validasi penyimpanan pada [backend](app/public/index.php), dan render tautan pada [template](app/views/page.php).

**Retest pada kandidat perbaikan:**

1. Login sebagai Staf A pada port `8081`, buat draft baru, dan tambahkan payload yang sama.
2. Penyimpanan harus ditolak dengan 422; form menampilkan pesan bahwa tautan harus URL HTTP/HTTPS valid.
3. Tambahkan URL HTTP/HTTPS normal seperti `https://example.test/referensi`; penyimpanan harus tetap berhasil. URL tidak perlu dibuka ke internet.
4. Kirim pengajuan dan periksa halaman sebagai admin. Payload yang ditolak tidak boleh menjadi tautan aktif.

Pemeriksaan data legacy memerlukan fixture internal dan tersedia pada suite browser di bagian referensi teknis. Dialog marker tidak membuktikan pencurian sesi atau pengambilalihan akun.

### 3. Upload executable — lampiran diproses sebagai PHP

**Lakukan pada aplikasi awal:**

1. Login sebagai Staf A pada port `8080` dan buat draft baru.
2. Unggah berkas [poc/marker.php](poc/marker.php) yang sudah tersedia melalui form lampiran.
3. Klik nama lampiran dengan label **Jalankan PHP**. UI membuka URL `/uploads/<nama-acak>.php` di tab baru.
4. Pastikan halaman menampilkan `EXECUTED_5`.
5. Bandingkan dengan **Unduh file PHP**, yang mengunduh source berkas. Unduhan source bukan bukti eksekusi.

Isi marker yang digunakan:

```php
<?php
header('Content-Type: text/plain');
echo 'EXECUTED_' . (2 + 3);
```

Hasil `EXECUTED_5` berasal dari perhitungan PHP. Marker tidak menerima perintah pengguna atau menjalankan perintah OS.

**Akar masalah:** berkas pengguna disimpan dalam direktori publik dengan ekstensi yang dapat diproses interpreter. Keberhasilan upload saja belum membuktikan eksekusi; hasil pemrosesan marker menjadi pembandingnya.

**Cara memperbaiki:** simpan upload di luar web root, batasi ekstensi dan MIME sesuai kebutuhan, periksa isi berkas/gambar, dan layani unduhan melalui endpoint yang memeriksa akses. Nonaktifkan akses atau eksekusi pada direktori upload publik. Profil `fixed` menerapkan private storage, allowlist PDF/TXT/PNG/JPG/JPEG, pemeriksaan konten, dan blokir `/uploads/`. Lihat [backend](app/public/index.php), [konfigurasi storage](app/bootstrap.php), dan [Apache](docker/apache.conf).

**Retest pada kandidat perbaikan:**

1. Login sebagai Staf A pada port `8081`, buat draft baru, dan unggah marker yang sama.
2. Upload PHP harus ditolak dengan 422 pada sesi serta token yang valid.
3. Unggah TXT normal atau PNG/JPEG asli maksimal 2 MB; unggahan dan unduhan oleh pemilik/admin harus tetap bekerja. Gambar asli juga harus dapat dipreview.
4. Akses `/uploads/marker.php` pada target perbaikan harus ditolak. Penolakan URL ini saja belum membuktikan validasi upload, sehingga tetap jalankan langkah kedua.

Bukti hanya menunjukkan eksekusi PHP dalam runtime aplikasi awal. Akses root, shell, dan keluar container tidak dibuktikan.

### Observasi browser tambahan

Untuk membandingkan perilaku browser, akses halaman PoC lokal pada `http://127.0.0.1:8090`:

- Klik **Muat vulnerable**, lalu **Muat fixed**, untuk membandingkan framing. Kandidat perbaikan mengirim CSP `frame-ancestors 'none'` dan `X-Frame-Options: DENY`. Kemampuan framing saja belum membuktikan serangan clickjacking terhadap tindakan sensitif.
- Klik **Baca informasi publik** untuk membaca endpoint publik dari origin berbeda tanpa kredensial. Wildcard CORS pada informasi publik ini bukan otomatis kebocoran data privat.

</details>

## Kunci jawaban — laporan, CVSS, dan prioritas perbaikan

<details>
<summary>Tampilkan pemetaan metrik, perhitungan tiga temuan, dan contoh isi laporan</summary>

Jawaban berikut memakai skenario serta asumsi dampak pada project RuangSurat. Cocokkan dengan bukti pengujianmu sendiri. Jika kondisi atau kemampuan yang terkonfirmasi berbeda, jelaskan perbedaannya sebelum menentukan skor. Referensi hasil project tersedia pada [laporan RuangSurat](laporan/Laporan_VAPT_Staging_RuangSurat_Kevin_McLoud_v1.1.md).

### 1. Pemetaan metrik CVSS

| Metrik       | VULN-01 — IDOR baca                                                           | VULN-02 — Stored XSS                                                      | VULN-03 — Upload PHP                                                                                                |
| ------------ | ------------------------------------------------------------------------------ | -------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| **AV** | `N`: akses dokumen melalui HTTP.                                             | `N`: penyimpanan dan pembukaan tautan melalui web.                       | `N`: upload dan pembukaan file melalui HTTP.                                                                       |
| **AC** | `L`: identifier dapat diganti langsung.                                      | `L`: tidak memerlukan race condition atau kondisi khusus.                | `L`: file dapat diunggah dan URL-nya dibuka langsung.                                                              |
| **PR** | `L`: pelaku memerlukan akun staf.                                            | `L`: pelaku adalah staf yang dapat menambahkan tautan ke draft sendiri.  | `L`: pelaku memerlukan akun staf dan draft sendiri.                                                                |
| **UI** | `N`: pemilik dokumen tidak perlu bertindak.                                  | `R`: admin perlu mengklik tautan.                                        | `N`: pengunggah dapat membuka file sendiri.                                                                        |
| **S**  | `U`: dampak tetap pada otoritas aplikasi yang sama.                          | `C`: input aplikasi menjadi script pada otoritas browser korban.         | `U`: dampak dinilai pada otoritas runtime aplikasi; tidak ada bukti keluar container.                              |
| **C**  | `L`: isi dan metadata dokumen pengguna lain terbaca.                         | `L`: kemampuan script mengakses informasi dalam konteks halaman korban.  | `H`: kemampuan kode dinilai dapat memengaruhi kerahasiaan secara luas pada data yang dapat dibaca proses aplikasi. |
| **I**  | `N`: skenario ini tidak memberikan kemampuan mengubah dokumen pengguna lain. | `L`: kemampuan script memengaruhi konten/interaksi dalam halaman korban. | `H`: kemampuan kode dinilai dapat mengubah data secara luas pada storage yang dapat ditulis proses aplikasi.       |
| **A**  | `N`: tidak ada dampak ketersediaan yang dinilai.                             | `N`: tidak ada dampak ketersediaan yang dinilai.                         | `H`: kemampuan kode dinilai dapat mengganggu aplikasi/data dalam otoritas proses secara luas.                      |

Untuk XSS, bukti minimum adalah dialog marker dalam sesi admin; C:L/I:L merupakan penilaian kemampuan script, bukan bukti pencurian data atau perubahan keputusan. Untuk upload PHP, bukti minimum adalah output `EXECUTED_5`; C:H/I:H/A:H merupakan penilaian kemampuan eksekusi kode pada runtime aplikasi beserta akses storage-nya. Marker tidak membuktikan bahwa pencurian, perubahan massal, atau DoS sudah dilakukan. Asumsi ini harus ditulis di laporan, bukan disembunyikan di balik angka.

### 2. Jawaban CVSS VULN-01 — IDOR baca

```text
CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:L/I:N/A:N

ISS = 1 − ((1 − 0.22) × (1 − 0) × (1 − 0))
    = 0.22

Impact = 6.42 × 0.22
       = 1.4124

Exploitability = 8.22 × 0.85 × 0.77 × 0.62 × 0.85
               = 2.83525473

Base Score = Roundup(min(1.4124 + 2.83525473, 10))
           = Roundup(4.24765473)
           = 4.3 → Medium
```

### 3. Jawaban CVSS VULN-02 — Stored XSS

Perhatikan bahwa `S:C` mengubah bobot `PR:L` menjadi **0.68**, dan rumus Base Score memakai faktor **1.08**.

```text
CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N

ISS = 1 − ((1 − 0.22) × (1 − 0.22) × (1 − 0))
    = 0.3916

Impact = 7.52 × (0.3916 − 0.029)
         − 3.25 × (0.3916 − 0.02)^15
       ≈ 2.726750844

Exploitability = 8.22 × 0.85 × 0.77 × 0.68 × 0.62
               = 2.268203784

Base Score = Roundup(min(1.08 × (Impact + Exploitability), 10))
           ≈ Roundup(5.394550998)
           = 5.4 → Medium
```

Angka bertanda `≈` dipendekkan untuk tampilan. Gunakan nilai presisi penuh saat menghitung hasil akhir.

### 4. Jawaban CVSS VULN-03 — Upload PHP

```text
CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H

ISS = 1 − ((1 − 0.56) × (1 − 0.56) × (1 − 0.56))
    = 0.914816

Impact = 6.42 × 0.914816
       = 5.87311872

Exploitability = 8.22 × 0.85 × 0.77 × 0.62 × 0.85
               = 2.83525473

Base Score = Roundup(min(5.87311872 + 2.83525473, 10))
           = Roundup(8.70837345)
           = 8.8 → High
```

Nilai ini bukan `PR:N` hanya karena URL file dapat dibuka tanpa login: skenario eksploitasi lengkap tetap memerlukan akun staf untuk mengunggah file. Nilai ini juga tidak otomatis Critical atau `S:C` hanya karena kode berjalan di server.

### 5. Jawaban dampak dan prioritas perbaikan

| Urutan       | Temuan                | Dampak dan alasan prioritas                                                                                                                     |
| ------------ | --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| **P1** | VULN-03 — Upload PHP | Kode pengguna berjalan pada runtime aplikasi. CVSS 8.8 / High; kemampuan proses terhadap data dan storage membuat perbaikan upload didahulukan. |
| **P2** | VULN-01 — IDOR baca  | Isi dan metadata dokumen dapat dibaca staf lain. CVSS 4.3 / Medium; pelanggaran kerahasiaan dapat dipicu tanpa tindakan pemilik dokumen.        |
| **P3** | VULN-02 — Stored XSS | Script dari tautan staf berjalan dalam browser admin. CVSS 5.4 / Medium; pemicu membutuhkan admin mengklik tautan tersebut.                     |

IDOR didahulukan dari XSS dalam contoh ini karena akses baca dapat dipicu langsung oleh staf tanpa interaksi korban. Skor CVSS tetap 4.3 untuk IDOR dan 5.4 untuk XSS. Seluruh temuan tetap perlu diperbaiki; jika sensitivitas dokumen atau alur review berubah, jelaskan penyesuaian prioritasnya.

### 6. Contoh ringkasan temuan untuk laporan

Gunakan tabel ini sebagai acuan setelah skenario terkait berhasil dibuktikan.

| ID      | Judul                                   | CVSS v3.1 / severity | Prioritas | Bukti minimum                                                                        |
| ------- | --------------------------------------- | -------------------- | --------- | ------------------------------------------------------------------------------------ |
| VULN-01 | Pembacaan dokumen lintas pemilik        | 4.3 / Medium         | P2        | Dokumen Staf B tampil dalam sesi Staf A, dengan identitas dan kepemilikan terlihat.  |
| VULN-02 | Stored XSS melalui tautan referensi     | 5.4 / Medium         | P3        | Tautan staf tersimpan lalu dialog marker muncul setelah diklik admin.                |
| VULN-03 | Upload PHP menghasilkan eksekusi server | 8.8 / High           | P1        | File terunggah dan URL executable menghasilkan `EXECUTED_5`, bukan unduhan source. |

Contoh narasi VULN-01, jika sesuai hasilmu:

> Pada profil vulnerable, Staf A dapat membaca dokumen milik Staf B dengan mengganti identifier pada URL detail. Hasil seharusnya adalah penolakan tanpa isi dokumen. Bukti memperlihatkan identitas Staf A sebagai pengguna aktif serta judul, isi, dan pemilik dokumen Staf B. Temuan dinilai CVSS v3.1 4.3 (Medium), dengan vector CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:L/I:N/A:N. Dampaknya adalah terbukanya isi dokumen kepada staf yang tidak berhak. Perbaikan diprioritaskan setelah upload PHP karena akses ini dapat dilakukan tanpa interaksi pemilik dokumen. Perbaikan yang diperlukan adalah pemeriksaan pemilik atau role admin pada setiap akses objek di server.

Lengkapi narasi tersebut dengan ID dokumen aktual, langkah reproduksi, screenshot, alasan metrik, dan hasil retest sendiri. Buat detail serupa untuk temuan lain yang berhasil dibuktikan.

### 7. Jawaban perbaikan dan hasil retest yang diharapkan

| Temuan  | Kontrol perbaikan                                                                          | Hasil yang diharapkan pada fixed                                                         | Tindakan sah pembanding                                                      |
| ------- | ------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| VULN-01 | Otorisasi per objek di server.                                                             | Baca lintas pemilik ditolak dengan 404 tanpa isi dokumen.                                | Pemilik dan admin tetap dapat membaca dokumen.                               |
| VULN-02 | Allowlist URL HTTP/HTTPS, render aman untuk data lama, serta CSP sebagai lapisan tambahan. | Penyimpanan skema JavaScript ditolak dengan 422; data legacy tidak menjadi tautan aktif. | URL HTTP/HTTPS sah tetap tersimpan dan alur review berjalan.                 |
| VULN-03 | Validasi tipe/konten, private storage, dan pencegahan eksekusi file upload.                | Upload PHP ditolak dengan 422; akses direktori upload publik diblokir.                   | TXT dan gambar asli tetap dapat diunggah, diunduh, dan dipreview sesuai hak. |

Tulis status **Fixed pada skenario yang diuji** hanya jika penolakan eksploitasi dan tindakan sah pembanding sudah diverifikasi. Jika eksploitasi masih bekerja, statusnya **Open**. Jika kondisi uji tidak valid, tulis **Belum dapat diverifikasi**. Status dicatat per profil/build; perbaikan pada `fixed` tidak mengubah keadaan `vulnerable`.

Untuk kesimpulan, prioritaskan upload PHP karena memberikan kemampuan eksekusi kode aplikasi. Jelaskan urutan penanganan IDOR dan XSS berdasarkan sensitivitas dokumen, interaksi korban, dan kebutuhan proses. Prioritas merupakan urutan penanganan, bukan skor tambahan. Jika urutan berbeda dari pengurutan CVSS, tulis alasannya. Jangan menyimpulkan akses root, pencurian sesi, atau pengambilalihan host dari marker saja.

</details>

## Referensi teknis — buka setelah eksplorasi manual

<details>
<summary>Tampilkan endpoint dan pengujian otomatis</summary>

Pengujian otomatis merupakan alat validasi tambahan. Jalankan langkah manual terlebih dahulu. Perintah di bawah dapat menambah data dan menimpa evidence otomatis lama; salin hasil yang ingin dipertahankan sebelum menjalankan ulang.

### Endpoint utama

| Method            | Endpoint                                                        | Fungsi                                          |
| ----------------- | --------------------------------------------------------------- | ----------------------------------------------- |
| `GET`           | `/health`                                                     | Healthcheck database.                           |
| `GET`           | `/api/session`                                                | Informasi sesi, token CSRF, mode, dan build.    |
| `POST`          | `/api/login`, `/api/logout`                                 | Masuk dan keluar sesi.                          |
| `GET`           | `/api/documents`                                              | Daftar dokumen sesuai role.                     |
| `POST`          | `/api/documents`                                              | Membuat draft.                                  |
| `GET`, `POST` | `/api/documents/{id}`                                         | Membaca detail atau mengubah judul/isi draft.   |
| `POST`          | `/api/documents/{id}/links`                                   | Menambahkan tautan referensi.                   |
| `POST`          | `/api/documents/{id}/attachments`                             | Mengunggah lampiran dengan multipart form.      |
| `POST`          | `/api/documents/{id}/submit`                                  | Mengirim draft untuk review.                    |
| `POST`          | `/api/documents/{id}/approve`, `/api/documents/{id}/reject` | Keputusan administrator.                        |
| `GET`           | `/api/attachments/{id}/download`                              | Mengunduh lampiran dengan pemeriksaan akses.    |
| `GET`           | `/api/attachments/{id}/preview`                               | Preview PNG/JPEG asli dengan pemeriksaan akses. |
| `GET`           | `/api/admin/users`                                            | Direktori pengguna khusus admin.                |
| `GET`           | `/api/public/info`                                            | Informasi publik fiktif untuk observasi CORS.   |

Request API yang mengubah state menggunakan header `X-CSRF-Token`, termasuk login dan logout. Ambil token dari `/api/session` sebelum login; login yang berhasil meregenerasi sesi dan mengembalikan token baru.

### Tes API

Pastikan kedua profil aplikasi sudah berjalan, lalu jalankan:

```bash
python3 tests/test_lab.py
```

Suite ini menggunakan pustaka standar Python dan mencakup autentikasi/logout, pembatasan role, IDOR baca, otorisasi perubahan, CSRF, alur draft sampai keputusan, validasi tautan, upload, unduhan, dan preview gambar. Pengujian membandingkan hasil yang diharapkan pada `vulnerable` dan `fixed`, termasuk fungsi sah pemilik/admin.

### Tes browser

Siapkan environment Python terpisah dan Chromium untuk Playwright:

```bash
python3 -m venv .venv
source .venv/bin/activate
python -m pip install playwright
python -m playwright install chromium
python3 tests/browser_lab.py
```

Chromium memerlukan library sistem yang sesuai dengan OS. Script memakai Chromium sistem jika tersedia, atau browser yang diinstal Playwright.

**Suite browser saat ini memerlukan ketiga layanan Docker Compose berjalan dan perintah `docker` tersedia.** Salah satu skenario memasukkan fixture tautan legacy melalui `docker compose exec` pada database profil `fixed`; menjalankan aplikasi hanya dengan `dev.sh` belum cukup untuk suite browser lengkap.

Tes browser mencakup alur UI staf/admin, dialog XSS, output PHP, filter tautan legacy, pencarian, preview dan unduh gambar, persetujuan, tampilan mobile, logout, framing, serta CORS publik. Screenshot dan catatan browser disimpan di `archive/evidence/validation/`.

### Pengumpulan evidence API

```bash
python3 scripts/collect-evidence.py
```

Script membuat draft uji dan merekam request/hasil untuk LAB-01, LAB-02, dan LAB-03 pada kedua profil. Nilai cookie dan token ditampilkan sebagai `[REDACTED]`. Keluaran JSON disimpan di `archive/evidence/validation/`.

Tes dan pengumpulan evidence menambahkan data uji. Nama file keluaran memakai pola tetap sehingga run berikutnya dapat menimpa evidence otomatis sebelumnya; salin hasil yang ingin dipertahankan sebelum menjalankan ulang.

</details>
