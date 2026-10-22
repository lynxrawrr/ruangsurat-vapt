<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
const MAX_UPLOAD = 2 * 1024 * 1024;
$mode = getenv('APP_MODE') ?: 'fixed';
if (!in_array($mode, ['vulnerable', 'fixed'], true)) {
    throw new RuntimeException('APP_MODE tidak valid');
}
define('LAB_MODE', $mode);
define('LAB_FIXED', $mode === 'fixed');
define('LAB_DATA', getenv('APP_DATA') ?: '/var/lib/ruangsurat');
define('PUBLIC_UPLOADS', __DIR__ . '/public/uploads');
define('PRIVATE_UPLOADS', LAB_DATA . '/uploads');

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('sqlite:' . LAB_DATA . '/lab.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    }
    return $pdo;
}
function query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return date(DATE_ATOM); }
function safe_url(string $url): bool {
    if (preg_match('/[\x00-\x20\x7f]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $p = parse_url($url);
    return is_array($p) && in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) && !isset($p['user']) && !isset($p['pass']);
}
function seed(): void {
    foreach ([LAB_DATA, LAB_DATA . '/sessions', PRIVATE_UPLOADS, PUBLIC_UPLOADS] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Tidak dapat membuat storage');
    }
    db()->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL, password TEXT NOT NULL, role TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS documents (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_id INTEGER NOT NULL REFERENCES users(id), title TEXT NOT NULL, body TEXT NOT NULL, category TEXT NOT NULL, status TEXT NOT NULL DEFAULT "draft", links TEXT NOT NULL DEFAULT "[]", created_at TEXT NOT NULL, updated_at TEXT NOT NULL, approved_by INTEGER REFERENCES users(id));
        CREATE TABLE IF NOT EXISTS attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE, name TEXT NOT NULL, stored TEXT NOT NULL, mime TEXT NOT NULL, size INTEGER NOT NULL);');
    if ((int)query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) return;
    db()->beginTransaction();
    try {
        foreach ([[1,'Alya Pratama','staf.a@example.test','RuangSurat!2026-A','staff'],[2,'Bima Saputra','staf.b@example.test','RuangSurat!2026-B','staff'],[3,'Nadia Putri','admin@example.test','RuangSurat!2026-Admin','admin']] as $u) {
            $u[3] = password_hash($u[3], PASSWORD_DEFAULT);
            query('INSERT INTO users VALUES (?,?,?,?,?)', $u);
        }
        foreach ([[1,'Pengadaan perangkat ruang rapat','Pengajuan dua perangkat presentasi untuk ruang rapat tim operasional.','Pengadaan','draft'],[2,'Surat tugas kunjungan lapangan','Permohonan persetujuan kunjungan lapangan untuk kegiatan inventaris aset.','Surat tugas','submitted'],[1,'Laporan kegiatan bulanan','Ringkasan kegiatan administrasi dan evaluasi proses internal bulan ini.','Laporan','submitted'],[2,'Permintaan perawatan printer','Perawatan berkala perangkat cetak di ruang administrasi.','Operasional','approved']] as $d) {
            query('INSERT INTO documents (owner_id,title,body,category,status,created_at,updated_at,approved_by) VALUES (?,?,?,?,?,?,?,?)', [...$d,now(),now(),$d[4] === 'approved' ? 3 : null]);
        }
        $file = 'seed-lampiran.txt';
        file_put_contents((LAB_FIXED ? PRIVATE_UPLOADS : PUBLIC_UPLOADS) . '/' . $file, "Lampiran dummy RuangSurat Lab.\nSeluruh data fiktif.\n");
        query('INSERT INTO attachments (document_id,name,stored,mime,size) VALUES (?,?,?,?,?)',[1,'catatan-pengadaan.txt',$file,'text/plain',filesize((LAB_FIXED ? PRIVATE_UPLOADS : PUBLIC_UPLOADS).'/'.$file)]);
        db()->commit();
    } catch (Throwable $error) { db()->rollBack(); throw $error; }
}
