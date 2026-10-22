# Pengantar VAPT sebelum Praktik RuangSurat

**Durasi penjelasan: sekitar 10–15 menit.**

## 1. Apa itu VAPT?

**VAPT** adalah *Vulnerability Assessment and Penetration Testing*, yaitu kegiatan mencari kelemahan keamanan, memvalidasi dampaknya, dan memberikan dasar perbaikan.

- **Vulnerability Assessment (VA):** mengidentifikasi dan menilai kelemahan. Pertanyaannya: “Bagian mana yang mungkin bermasalah?”
- **Penetration Testing (PT):** menguji apakah kelemahan tersebut benar-benar dapat dimanfaatkan dalam batas pengujian yang disepakati. Pertanyaannya: “Apa yang bisa dilakukan melalui kelemahan ini, dan apa buktinya?”

Keduanya saling melengkapi. Hasil scanner perlu diverifikasi, sedangkan pengujian manual membantu menemukan masalah pada hak akses dan aturan bisnis.

## 2. Apa yang ingin dilindungi?

Keamanan informasi memiliki tiga tujuan dasar:

| Tujuan | Makna | Contoh pada RuangSurat |
| --- | --- | --- |
| **Confidentiality** — kerahasiaan | Informasi hanya dapat dibaca pihak yang berhak. | Dokumen privat staf tidak dapat dibaca sembarang pengguna. |
| **Integrity** — integritas | Data dan proses hanya dapat diubah sesuai kewenangan. | Keputusan pengajuan hanya diberikan admin pada status yang tepat. |
| **Availability** — ketersediaan | Layanan tersedia ketika diperlukan. | Pengguna dapat mengakses aplikasi dan mengajukan dokumen. |

Pada praktik ini kita memeriksa perlindungan tersebut melalui fitur aplikasi. Pengujian gangguan layanan atau DoS berada di luar cakupan.

## 3. Kenali aplikasi dan aturan normalnya

RuangSurat merupakan aplikasi pengajuan dokumen internal. Staf membuat draft, menambahkan lampiran atau tautan, lalu mengirim pengajuan. Admin meninjau dan menyetujui atau menolaknya.

Pegang aturan berikut sebagai acuan pengujian:

- Staf membaca dokumen sendiri dan mengelola draft sendiri.
- Admin dapat membaca dokumen lintas staf dan memberi keputusan pada pengajuan yang menunggu review.
- Admin tetap harus menjadi pemilik untuk mengelola sebuah draft.
- Draft yang sudah dikirim tidak dapat diubah lagi.
- Lampiran privat mengikuti hak akses dokumennya.

**Autentikasi** menjawab “Siapa kamu?”, sedangkan **otorisasi** menjawab “Apa yang boleh kamu lakukan terhadap objek ini?” Berhasil login belum berarti boleh mengakses semua dokumen.

## 4. Bagaimana alur pengujiannya?

Lab menggunakan pendekatan **grey-box**: peserta mendapat akun dan aturan akses, kemudian menguji perilaku melalui browser dan HTTP. Source dibahas saat pembahasan akar masalah.

Kerangka kegiatan mengikuti tujuh bagian PTES:

1. **Pre-engagement:** sepakati target, akun, batas, dan aturan pengujian.
2. **Intelligence gathering / recon:** kenali aplikasi, fitur, dan respons HTTP.
3. **Threat modeling:** tentukan aset, pelaku, kemampuan awal, dan aturan yang mungkin dilanggar.
4. **Vulnerability analysis:** susun dugaan kelemahan dan periksa perilakunya.
5. **Exploitation:** buktikan dugaan dengan tindakan minimum yang diperlukan.
6. **Post-exploitation:** evaluasi kemampuan dan dampak yang sudah terbukti; pada lab ini tidak perlu serangan lanjutan.
7. **Reporting:** komunikasikan bukti, dampak, batas pengujian, dan rekomendasi.

Setelah perbaikan tersedia, lakukan **retest**. Susun laporan ringkas berisi bukti, dampak, penilaian CVSS, prioritas perbaikan, rekomendasi, dan hasil retest. Format serta contoh perhitungannya tersedia di [panduan hands-on](../PANDUAN_LAB.md).

## 5. Apa yang dilakukan saat praktik?

Gunakan pola sederhana ini pada fitur yang dipilih:

**Jalankan fitur normal → ambil request → ubah satu hal → periksa hasil → bandingkan.**

- **Browser** untuk menjalankan fitur dan melihat hasilnya.
- **Burp Suite** untuk mengamati HTTP serta mengirim ulang request melalui Repeater.
- **curl** untuk pemeriksaan respons HTTP sederhana.
- **Nmap dan Nuclei** bersifat opsional untuk membantu recon sesuai panduan dan scope.

Pastikan request normal bekerja terlebih dahulu. Saat berpindah akun, gunakan sesi aktif yang sesuai. Tab biasa dalam profil browser yang sama berbagi cookie.

## 6. Kapan sesuatu disebut temuan?

Temuan membutuhkan **aturan yang dilanggar dan bukti yang dapat diulang**.

- HTTP `200` saja belum membuktikan akses tidak sah; periksa isi dan identitas pemilik data.
- Input tersimpan belum membuktikan script berjalan pada browser pengguna lain.
- Upload berhasil belum membuktikan file dijalankan sebagai kode di server.
- Hasil scanner kosong belum membuktikan aplikasi aman.

Jelaskan akun yang digunakan, objek uji, tindakan, hasil yang seharusnya, dan hasil aktual. Batasi kesimpulan pada kemampuan yang benar-benar terbukti.

## 7. Bagaimana memastikan perbaikan bekerja?

Pada target retest dari instruktur, login ulang dan gunakan data uji yang sesuai. Ulangi skenario dengan kemampuan awal pelaku yang setara, lalu pastikan:

1. Tindakan yang melanggar aturan sudah tertahan.
2. Tindakan sah oleh pengguna yang berhak tetap berjalan.

Cookie salah, token kedaluwarsa, objek tidak ada, atau layanan mati dapat membuat request gagal. Kegagalan tersebut belum membuktikan kontrol keamanan sudah diperbaiki.

## 8. Batas praktik

Mulai dari **http://127.0.0.1:8080**, atau alamat yang diberikan instruktur. Listener proxy Burp menggunakan **127.0.0.1:8082** agar tidak berbenturan dengan port aplikasi.

Gunakan akun dan data fiktif lab. Jangan menguji target internet, port lain, host, atau Docker di luar scope. Hindari DoS, shell/perintah OS, penghapusan massal, dan reset lingkungan bersama. Berhenti setelah bukti minimum diperoleh; sampaikan kepada instruktur jika muncul data nyata atau layanan bermasalah.

**Sebelum mulai, tentukan: “Aturan apa yang saya uji, siapa pelakunya, dan hasil apa yang akan membuktikan pelanggaran?”**

---

Rujukan: aturan dan langkah pengujian pada [README](../README.md) serta [panduan hands-on](../PANDUAN_LAB.md); panduan metodologi [PTES](http://www.pentest-standard.org/index.php/Main_Page) dan [OWASP WSTG v4.2](https://owasp.org/www-project-web-security-testing-guide/v42/).
