<?php
$statusLabels=['draft'=>'Draft','submitted'=>'Menunggu review','approved'=>'Disetujui','rejected'=>'Ditolak'];
$titles=['dashboard'=>'Pengajuan dokumen','new'=>'Pengajuan baru','detail'=>'Detail pengajuan','users'=>'Direktori pengguna','forbidden'=>'Akses terbatas','missing'=>'Halaman tidak ditemukan','login'=>'Selamat datang kembali'];
$badge = static function(string $status) use ($statusLabels): string { return '<span class="status '.e($status).'">'.e($statusLabels[$status]??$status).'</span>'; };
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf']) ?>">
  <title><?= e($titles[$page]) ?> · RuangSurat</title>
  <link rel="stylesheet" href="/assets/style.css">
  <script src="/assets/app.js" defer></script>
</head>
<body data-mode="<?= e(LAB_MODE) ?>">
<?php if ($page==='login'): ?>
<main class="login-layout">
  <section class="login-story">
    <a class="brand" href="/"><span class="brand-icon">R</span> RuangSurat<span class="brand-dot">.</span></a>
    <div class="story-content"><p class="eyebrow">RUANG KERJA ADMINISTRASI</p><h1>Lebih rapi.<br>Lebih mudah.<br>Selangkah maju.</h1><p>Kelola pengajuan, bagikan dokumen, dan selesaikan persetujuan dalam satu ruang.</p><div class="paper-art" aria-hidden="true"><div class="paper"><span>RS / 026</span><strong>Surat yang tertata,<br>pekerjaan yang tuntas.</strong><i></i><i></i><i></i><b>✓</b></div><div class="seal">TERORGANISIR<br>SEJAK AWAL</div></div></div>
    <small>RuangSurat Lab · Seluruh data bersifat fiktif</small>
  </section>
  <section class="login-form-wrap"><div class="login-card"><span class="environment <?= e(LAB_MODE) ?>">LAB / <?= e(strtoupper(LAB_MODE)) ?></span><p class="eyebrow">MULAI HARI ANDA</p><h2>Selamat datang kembali</h2><p class="muted">Masuk untuk melanjutkan pekerjaan Anda.</p>
    <form data-action="login"><label>Email<input type="email" name="email" autocomplete="username" placeholder="nama@example.test" required></label><label>Password<input type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required></label><button type="submit" class="primary full">Masuk ke ruang kerja <span>→</span></button><p class="form-message" role="alert"></p></form>
    <p class="login-foot">Gunakan akun praktikum yang tercantum dalam README.</p>
  </div></section>
</main>
<?php else: ?>
<aside class="sidebar">
  <a class="brand" href="/"><span class="brand-icon">R</span> RuangSurat<span class="brand-dot">.</span></a>
  <div class="workspace"><span class="workspace-avatar">RS</span><div><strong>Ruang kerja internal</strong><small>Administrasi & dokumen</small></div></div>
  <p class="nav-label">RUANG KERJA</p>
  <nav><a class="<?= in_array($page,['dashboard','detail'])?'active':'' ?>" href="/"><span>▤</span> Pengajuan dokumen</a><a class="<?= $page==='new'?'active':'' ?>" href="/documents/new"><span>＋</span> Buat pengajuan</a><?php if ($u['role']==='admin'): ?><a class="<?= $page==='users'?'active':'' ?>" href="/admin/users"><span>▦</span> Direktori pengguna</a><?php endif ?></nav>
  <div class="sidebar-note"><span>✦</span><strong>Dokumen tertata,<br>kolaborasi lancar.</strong><p>Satu tempat untuk setiap langkah pengajuan Anda.</p></div>
  <div class="profile"><span class="avatar"><?= e(substr($u['name'],0,1)) ?></span><div><strong><?= e($u['name']) ?></strong><small><?= $u['role']==='admin'?'Administrator':'Staf' ?></small></div><button type="button" class="logout" id="logout" data-logout title="Keluar" aria-label="Keluar">↪</button></div>
</aside>
<div class="main-shell"><header class="topbar"><span>Ruang kerja <span class="crumb">/</span> <strong><?= e($titles[$page]) ?></strong></span><span class="environment <?= e(LAB_MODE) ?>">LAB / <?= e(strtoupper(LAB_MODE)) ?></span><button class="mobile-logout secondary" data-logout type="button">Keluar</button></header>
<main class="content"><div id="global-message" role="alert"></div>
<?php if ($page==='dashboard'):
  $docs=query('SELECT d.*,u.name AS owner_name FROM documents d JOIN users u ON u.id=d.owner_id'.($u['role']==='admin'?'':' WHERE d.owner_id=?').' ORDER BY d.id DESC',$u['role']==='admin'?[]:[$u['id']])->fetchAll();
  $counts=array_count_values(array_column($docs,'status'));
?>
<section class="page-heading"><div><p class="eyebrow">ADMINISTRASI YANG LEBIH TERATUR</p><h1>Pengajuan dokumen<span class="heading-dot">.</span></h1><p class="muted"><?= $u['role']==='admin'?'Tinjau pengajuan tim dan lanjutkan proses persetujuan.':'Pantau dokumen dan setiap langkah pengajuan Anda.' ?></p></div><a class="button primary" href="/documents/new">＋ Pengajuan baru</a></section>
<section class="stats" aria-label="Ringkasan pengajuan"><article><span>Total pengajuan</span><strong><?= count($docs) ?></strong><small>Dokumen dalam ruang kerja</small><b>▤</b></article><article><span>Menunggu review</span><strong><?= $counts['submitted']??0 ?></strong><small>Siap untuk ditindaklanjuti</small><b class="amber">◷</b></article><article><span>Disetujui</span><strong><?= $counts['approved']??0 ?></strong><small>Proses administrasi selesai</small><b class="teal">✓</b></article></section>
<section class="panel"><div class="panel-heading"><div><h2>Semua pengajuan <span class="count"><?= count($docs) ?></span></h2><p class="muted">Daftar dokumen yang dapat Anda akses.</p></div><label class="search"><span>⌕</span><input id="document-search" type="search" aria-label="Cari pengajuan" placeholder="Cari judul atau pengaju..."></label></div><div class="tabs" role="group" aria-label="Filter status"><button class="selected" data-filter="all">Semua</button><button data-filter="draft">Draft</button><button data-filter="submitted">Menunggu review</button><button data-filter="approved">Disetujui</button><button data-filter="rejected">Ditolak</button></div>
<div class="table-scroll"><table><thead><tr><th>JUDUL DOKUMEN</th><th>PENGAJU</th><th>STATUS</th><th>TANGGAL</th><th></th></tr></thead><tbody id="document-table"><?php foreach ($docs as $row): ?><tr data-status="<?= e($row['status']) ?>"><td><a class="document-title" href="/documents/<?= (int)$row['id'] ?>"><span class="file-icon">▤</span><span><?= e($row['title']) ?><small>RS-<?= sprintf('%03d',$row['id']) ?> · <?= e($row['category']) ?></small></span></a></td><td><?= e($row['owner_name']) ?></td><td><?= $badge($row['status']) ?></td><td class="muted"><?= date('d M Y',strtotime($row['created_at'])) ?></td><td><a class="row-arrow" href="/documents/<?= (int)$row['id'] ?>" aria-label="Buka <?= e($row['title']) ?>">↗</a></td></tr><?php endforeach ?></tbody></table></div><p class="empty <?= $docs?'hidden':'' ?>" id="empty-documents">Belum ada pengajuan yang sesuai.</p><div class="panel-footer">Tersimpan di ruang kerja Anda <span>RuangSurat / Internal</span></div></section>
<?php elseif ($page==='new'): ?>
<a class="back-link" href="/">← Kembali ke pengajuan</a><section class="page-heading"><div><p class="eyebrow">MULAI DARI DRAFT</p><h1>Pengajuan baru<span class="heading-dot">.</span></h1><p class="muted">Lengkapi informasi, lalu tambahkan lampiran sebelum dikirim.</p></div></section>
<section class="panel form-panel"><form data-action="create"><h2>Informasi dokumen</h2><label>Judul pengajuan<input name="title" required maxlength="160" placeholder="Contoh: Pengadaan perangkat ruang rapat"></label><label>Kategori<select name="category"><option>Pengadaan</option><option>Surat tugas</option><option>Laporan</option><option>Operasional</option></select></label><label>Isi / keterangan<textarea name="body" required rows="7" maxlength="10000" placeholder="Jelaskan tujuan dan rincian pengajuan..."></textarea></label><div class="form-bottom"><span class="muted">Pengajuan disimpan sebagai draft.</span><button class="primary" type="submit">Simpan draft →</button></div><p class="form-message" role="alert"></p></form></section>
<?php elseif ($page==='detail'): $canEdit=(int)$d['owner_id']===(int)$u['id'] && $d['status']==='draft'; ?>
<a class="back-link" href="/">← Kembali ke pengajuan</a><section class="page-heading"><div><p class="eyebrow">RS-<?= sprintf('%03d',$d['id']) ?> / <?= e(strtoupper($d['category'])) ?></p><h1><?= e($d['title']) ?></h1><p class="muted">Diajukan oleh <?= e($d['owner_name']) ?> · <?= date('d M Y, H:i',strtotime($d['created_at'])) ?> WIB</p></div><?= $badge($d['status']) ?></section>
<div class="detail-grid"><div><section class="panel detail-panel"><h2>Informasi pengajuan</h2><p class="document-body"><?= nl2br(e($d['body'])) ?></p><?php if ($canEdit): ?><details><summary>Ubah draft</summary><form data-action="edit" data-id="<?= (int)$d['id'] ?>"><label>Judul<input name="title" required maxlength="160" value="<?= e($d['title']) ?>"></label><label>Keterangan<textarea name="body" rows="5" required maxlength="10000"><?= e($d['body']) ?></textarea></label><button class="secondary" type="submit">Simpan perubahan</button><p class="form-message" role="alert"></p></form></details><?php endif ?></section>
<section class="panel detail-panel">
<h2>Lampiran <span class="count"><?= count($d['attachments']) ?></span></h2>
<?php foreach ($d['attachments'] as $a):
    $isPhp=isset($a['execution_url']);
    $isImage=!$isPhp && in_array($a['mime'],['image/png','image/jpeg'],true) && ((int)$d['owner_id']===(int)$u['id'] || $u['role']==='admin');
    $attachmentUrl='/api/attachments/'.(int)$a['id'];
    $openUrl=$isPhp?$a['execution_url']:$attachmentUrl.($isImage?'/preview':'/download');
?>
<div class="attachment-item">
<a class="attachment" href="<?= e($openUrl) ?>"<?= ($isImage || $isPhp)?' target="_blank" rel="noopener"':'' ?>><span class="file-icon">▤</span><span><?= e($a['name']) ?><small><?= number_format($a['size']/1024,1) ?> KB<?= $isPhp?' · Jalankan PHP':($isImage?' · Buka gambar ukuran penuh':'') ?></small></span><b><?= ($isImage || $isPhp)?'↗':'↓' ?></b></a>
<?php if ($isImage): ?>
<a class="attachment-preview" href="<?= $attachmentUrl ?>/preview" target="_blank" rel="noopener"><img src="<?= $attachmentUrl ?>/preview" alt="<?= e($a['name']) ?>" loading="lazy"></a>
<a class="attachment-download" href="<?= $attachmentUrl ?>/download">↓ Unduh gambar</a>
<?php elseif ($isPhp): ?>
<a class="attachment-download" href="<?= $attachmentUrl ?>/download">↓ Unduh file PHP</a>
<?php endif ?>
</div>
<?php endforeach ?>
<?php if (!$d['attachments']): ?><p class="muted">Belum ada lampiran.</p><?php endif ?>
<?php if ($canEdit): ?><form data-action="upload" data-id="<?= (int)$d['id'] ?>"><label class="upload-box">Tambahkan lampiran <small>Maksimal 2 MB per berkas</small><input name="file" type="file" required></label><button class="secondary" type="submit">Unggah berkas</button><p class="form-message" role="alert"></p></form><?php endif ?>
</section>
<section class="panel detail-panel"><h2>Tautan referensi</h2><div class="links-list"><?php foreach ($d['links'] as $link): ?><?php if (LAB_FIXED && !safe_url($link['url'])): ?><span class="blocked-link"><?= e($link['label']) ?> <small>Tautan tidak valid dinonaktifkan</small></span><?php else: ?><a class="reference-link" href="<?= e($link['url']) ?>">↗ <?= e($link['label']) ?></a><?php endif ?><?php endforeach ?></div><?php if (!$d['links']): ?><p class="muted">Belum ada tautan referensi.</p><?php endif ?><?php if ($canEdit): ?><form data-action="link" data-id="<?= (int)$d['id'] ?>"><div class="form-row"><label>Nama tautan<input name="label" required maxlength="100" placeholder="Dokumen pendukung"></label><label>Alamat tautan<input name="url" required maxlength="2000" placeholder="https://..."></label></div><button class="secondary" type="submit">Tambahkan tautan</button><p class="form-message" role="alert"></p></form><?php endif ?></section></div>
<aside class="panel process-panel"><p class="eyebrow">ALUR PENGAJUAN</p><h2>Setiap langkah tercatat.</h2><ol class="timeline"><li class="done"><strong>Draft dibuat</strong><small>Informasi awal disimpan</small></li><li class="<?= $d['status']!=='draft'?'done':'' ?>"><strong>Dikirim untuk review</strong><small>Dokumen ditinjau administrator</small></li><li class="<?= in_array($d['status'],['approved','rejected'])?'done':'' ?>"><strong>Keputusan akhir</strong><small><?= e($statusLabels[$d['status']]) ?></small></li></ol><?php if ($canEdit): ?><button class="primary full" data-action-button="submit" data-id="<?= (int)$d['id'] ?>">Kirim pengajuan →</button><p class="muted hint">Draft tidak dapat diubah setelah dikirim.</p><?php elseif ($u['role']==='admin' && $d['status']==='submitted'): ?><button class="primary full" data-action-button="approve" data-id="<?= (int)$d['id'] ?>">✓ Setujui pengajuan</button><button class="secondary full" data-action-button="reject" data-id="<?= (int)$d['id'] ?>">Tolak pengajuan</button><?php endif ?></aside></div>
<?php elseif ($page==='users'): ?>
<section class="page-heading"><div><p class="eyebrow">ADMINISTRASI PENGGUNA</p><h1>Direktori pengguna<span class="heading-dot">.</span></h1><p class="muted">Daftar pengguna dalam ruang kerja internal.</p></div></section><section class="panel"><div class="table-scroll"><table><thead><tr><th>NAMA</th><th>EMAIL</th><th>ROLE</th></tr></thead><tbody id="users-table"><tr><td colspan="3">Memuat direktori...</td></tr></tbody></table></div></section>
<?php else: ?><section class="panel empty-state"><p class="eyebrow"><?= $page==='forbidden'?'403':'404' ?></p><h1><?= e($titles[$page]) ?></h1><p class="muted"><?= $page==='forbidden'?'Halaman ini hanya tersedia untuk administrator.':'Halaman yang Anda cari tidak tersedia.' ?></p><a class="button primary" href="/">Kembali ke ruang kerja</a></section><?php endif ?>
<footer class="footer">RuangSurat<span>.</span> <small>Ruang kerja yang lebih teratur.</small><small>Lab lokal · Data fiktif</small></footer>
</main></div>
<?php endif ?>
</body></html>
