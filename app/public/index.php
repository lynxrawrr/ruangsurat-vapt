<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');
if (LAB_FIXED) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; object-src 'none'; form-action 'self'");
    header('X-Frame-Options: DENY');
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
if ($path === '/health') {
    try { query('SELECT 1 FROM users LIMIT 1'); header('Content-Type: application/json'); echo '{"status":"ok"}'; }
    catch (Throwable $e) { http_response_code(503); echo '{"status":"unavailable"}'; }
    exit;
}
session_name('RUANGSURAT_' . strtoupper(LAB_MODE));
session_save_path(LAB_DATA . '/sessions');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','path'=>'/']);
ini_set('session.use_strict_mode','1');
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(24));
function json_response(array $data, int $code = 200): never {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}
function fail(string $message, int $code = 400): never { json_response(['error'=>$message],$code); }
function input(): array {
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $data = json_decode(file_get_contents('php://input'),true);
        if (!is_array($data)) fail('JSON tidak valid');
        return $data;
    }
    return $_POST;
}
function text_field(array $data, string $key, int $max): string {
    if (!isset($data[$key]) || !is_string($data[$key])) fail('Field '.$key.' wajib diisi');
    $value = trim($data[$key]);
    if ($value === '' || strlen($value)>$max) fail('Panjang '.$key.' tidak valid');
    return $value;
}
function current_user(): ?array {
    return isset($_SESSION['uid']) ? (query('SELECT id,name,email,role FROM users WHERE id=?',[$_SESSION['uid']])->fetch() ?: null) : null;
}
function user_required(): array { return current_user() ?? fail('Silakan login',401); }
function document(int $id, array $user, bool $readDemo = false): array {
    $d = query('SELECT d.*,u.name AS owner_name FROM documents d JOIN users u ON u.id=d.owner_id WHERE d.id=?',[$id])->fetch();
    // LAB-01: only document reads omit ownership checks in the vulnerable profile.
    if (!$d || ($user['role']!=='admin' && (int)$d['owner_id']!==(int)$user['id'] && !($readDemo && !LAB_FIXED))) fail('Dokumen tidak ditemukan',404);
    $d['links'] = json_decode($d['links'],true);
    $d['attachments'] = query('SELECT id,name,size,mime,stored FROM attachments WHERE document_id=?',[$id])->fetchAll();
    foreach ($d['attachments'] as &$a) {
        $a['url'] = '/api/attachments/'.$a['id'].'/download';
        if (!LAB_FIXED && pathinfo($a['stored'],PATHINFO_EXTENSION)==='php') $a['execution_url']='/uploads/'.rawurlencode($a['stored']);
        unset($a['stored']);
    }
    return $d;
}
function editable(array $d, array $u): void {
    if ((int)$d['owner_id']!==(int)$u['id'] || $d['status']!=='draft') fail('Hanya pemilik dapat mengubah draft',403);
}
try {
    if (str_starts_with($path,'/api/')) {
        if ($method !== 'GET' && !hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('CSRF token tidak valid',403);
        if ($path==='/api/session' && $method==='GET') json_response(['user'=>current_user(),'csrf'=>$_SESSION['csrf'],'mode'=>LAB_MODE,'build'=>getenv('APP_BUILD') ?: 'local']);
        if ($path==='/api/login' && $method==='POST') {
            $data=input(); $email=text_field($data,'email',200); $password=text_field($data,'password',200);
            $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
            if (!$u || !password_verify($password,$u['password'])) fail('Email atau password salah',401);
            session_regenerate_id(true); $_SESSION=['uid'=>(int)$u['id'],'csrf'=>bin2hex(random_bytes(24))];
            json_response(['user'=>current_user(),'csrf'=>$_SESSION['csrf']]);
        }
        if ($path==='/api/logout' && $method==='POST') {
            $_SESSION=[]; session_destroy(); setcookie(session_name(),'', ['expires'=>1,'path'=>'/','httponly'=>true,'samesite'=>'Lax']); json_response(['ok'=>true]);
        }
        if ($path==='/api/public/info' && $method==='GET') {
            header('Access-Control-Allow-Origin: *'); json_response(['application'=>'RuangSurat Lab','data'=>'Informasi publik fiktif']);
        }
        $u=user_required();
        if ($path==='/api/admin/users' && $method==='GET') {
            if ($u['role']!=='admin') fail('Akses khusus administrator',403);
            json_response(['users'=>query('SELECT id,name,email,role FROM users ORDER BY id')->fetchAll()]);
        }
        if ($path==='/api/documents' && $method==='GET') {
            $where=$u['role']==='admin' ? '' : ' WHERE d.owner_id=?';
            json_response(['documents'=>query('SELECT d.id,d.title,d.category,d.status,d.owner_id,d.created_at,u.name AS owner_name FROM documents d JOIN users u ON u.id=d.owner_id'.$where.' ORDER BY d.id DESC', $u['role']==='admin'?[]:[$u['id']])->fetchAll()]);
        }
        if ($path==='/api/documents' && $method==='POST') {
            $data=input(); $title=text_field($data,'title',160); $body=text_field($data,'body',10000); $category=text_field($data,'category',60);
            if (!in_array($category,['Pengadaan','Surat tugas','Laporan','Operasional'],true)) fail('Kategori tidak valid');
            query('INSERT INTO documents (owner_id,title,body,category,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$u['id'],$title,$body,$category,now(),now()]);
            json_response(['id'=>(int)db()->lastInsertId()],201);
        }
        if (preg_match('~^/api/documents/(\d+)(?:/(links|attachments|submit|approve|reject))?$~',$path,$m)) {
            $action=$m[2]??''; $d=document((int)$m[1],$u,$method==='GET' && $action==='');
            if ($method==='GET' && $action==='') json_response(['document'=>$d]);
            if ($method==='POST') {
                if (in_array($action,['approve','reject'],true)) {
                    if ($u['role']!=='admin') fail('Hanya admin yang dapat memberi keputusan',403);
                    if ($d['status']!=='submitted') fail('Pengajuan belum menunggu persetujuan',409);
                    query('UPDATE documents SET status=?,approved_by=?,updated_at=? WHERE id=? AND status=?',[$action==='approve'?'approved':'rejected',$u['id'],now(),$d['id'],'submitted']);
                    json_response(['ok'=>true]);
                }
                editable($d,$u);
                if ($action==='') {
                    $data=input(); query('UPDATE documents SET title=?,body=?,updated_at=? WHERE id=?',[text_field($data,'title',160),text_field($data,'body',10000),now(),$d['id']]); json_response(['ok'=>true]);
                }
                if ($action==='submit') { query('UPDATE documents SET status=?,updated_at=? WHERE id=?',['submitted',now(),$d['id']]); json_response(['ok'=>true]); }
                if ($action==='links') {
                    $data=input(); $url=text_field($data,'url',2000); $label=text_field($data,'label',100);
                    // LAB-02: vulnerable stores active URI schemes; fixed uses an HTTP(S) allowlist.
                    if (LAB_FIXED && !safe_url($url)) fail('Tautan harus URL http atau https yang valid',422);
                    if (count($d['links'])>=10) fail('Maksimal 10 tautan');
                    $links=$d['links']; $links[]=['label'=>$label,'url'=>$url];
                    query('UPDATE documents SET links=?,updated_at=? WHERE id=?',[json_encode($links),now(),$d['id']]); json_response(['ok'=>true],201);
                }
                if ($action==='attachments') {
                    $f=$_FILES['file']??null;
                    if (!$f || !is_string($f['name']??null) || $f['error']!==UPLOAD_ERR_OK || $f['size']<1 || $f['size']>MAX_UPLOAD) fail('Pilih file valid, maksimal 2 MB',422);
                    $name=basename(str_replace('\\','/',$f['name'])); $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
                    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                    if (LAB_FIXED) {
                        $allow=['pdf'=>'application/pdf','txt'=>'text/plain','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg'];
                        if (!isset($allow[$ext]) || $allow[$ext]!==$mime) fail('Tipe file tidak diizinkan. Gunakan PDF, TXT, PNG, atau JPG asli',422);
                        if (str_contains(strtolower(file_get_contents($f['tmp_name'])),'<?')) fail('Konten file tidak diizinkan',422);
                        if (in_array($ext,['png','jpg','jpeg'],true) && !@getimagesize($f['tmp_name'])) fail('Gambar tidak valid',422);
                    }
                    // LAB-03: vulnerable places user-controlled executable types in the web root.
                    $stored=bin2hex(random_bytes(16)).(preg_match('/^[a-z0-9]{1,10}$/',$ext)?'.'.$ext:'.bin');
                    $target=(LAB_FIXED?PRIVATE_UPLOADS:PUBLIC_UPLOADS).'/'.$stored;
                    if (!move_uploaded_file($f['tmp_name'],$target)) fail('Penyimpanan file gagal',500);
                    chmod($target,0644);
                    query('INSERT INTO attachments (document_id,name,stored,mime,size) VALUES (?,?,?,?,?)',[$d['id'],$name,$stored,$mime,$f['size']]);
                    $id=(int)db()->lastInsertId(); json_response(['id'=>$id,'url'=>LAB_FIXED?'/api/attachments/'.$id.'/download':'/uploads/'.$stored],201);
                }
            }
        }
        if (preg_match('~^/api/attachments/(\d+)/(download|preview)$~',$path,$m) && $method==='GET') {
            $a=query('SELECT * FROM attachments WHERE id=?',[(int)$m[1]])->fetch();
            if (!$a) fail('Lampiran tidak ditemukan',404);
            document((int)$a['document_id'],$u);
            $file=(LAB_FIXED?PRIVATE_UPLOADS:PUBLIC_UPLOADS).'/'.$a['stored'];
            if (!is_file($file)) fail('File tidak ditemukan',404);
            $preview=$m[2]==='preview';
            $mime='application/octet-stream';
            if ($preview) {
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);
                $image=@getimagesize($file);
                if (!in_array($mime,['image/png','image/jpeg'],true) || !$image || $image['mime']!==$mime) fail('Pratinjau hanya tersedia untuk gambar PNG atau JPG asli',415);
            }
            header('Content-Type: '.$mime);
            header("Content-Disposition: ".($preview?'inline':'attachment')."; filename*=UTF-8''".rawurlencode($a['name']));
            header("Content-Security-Policy: sandbox; default-src 'none'");
            header('Content-Length: '.filesize($file)); session_write_close(); readfile($file); exit;
        }
        fail('Endpoint tidak ditemukan',404);
    }
    $u=current_user();
    if (!$u && $path!=='/login') { header('Location: /login'); exit; }
    if ($u && $path==='/login') { header('Location: /'); exit; }
    if ($path==='/admin/users' && $u['role']!=='admin') { http_response_code(403); $page='forbidden'; }
    elseif ($path==='/login') $page='login';
    elseif ($path==='/') $page='dashboard';
    elseif ($path==='/documents/new') $page='new';
    elseif ($path==='/admin/users') $page='users';
    elseif (preg_match('~^/documents/(\d+)$~',$path,$m)) { $d=document((int)$m[1],$u,true); $page='detail'; }
    else { http_response_code(404); $page='missing'; }
    require dirname(__DIR__).'/views/page.php';
} catch (Throwable $error) {
    error_log((string)$error);
    if (str_starts_with($path,'/api/')) fail('Terjadi kesalahan internal',500);
    http_response_code(500); echo 'Terjadi kesalahan internal. Periksa log aplikasi.';
}
