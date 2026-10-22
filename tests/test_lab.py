"""Integration tests against the running, local-only lab. Creates dummy documents."""
import http.cookiejar
import base64
import json
import unittest
import urllib.error
import urllib.request
from pathlib import Path


class Client:
    def __init__(self, port):
        self.base = f'http://127.0.0.1:{port}'
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = self.call('/api/session')[1]['csrf']

    def call(self, path, data=None, raw=None, content_type=None, csrf=True):
        headers = {}
        if data is not None:
            raw = json.dumps(data).encode()
            content_type = 'application/json'
        if raw is not None:
            headers['Content-Type'] = content_type
            if csrf:
                headers['X-CSRF-Token'] = self.csrf
        req = urllib.request.Request(self.base + path, data=raw, headers=headers)
        try:
            response = self.opener.open(req, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read()
        result = json.loads(body) if 'application/json' in response.headers.get('Content-Type', '') else body
        return response.code, result, response.headers

    def login(self, who):
        account = {'a': ('staf.a', 'A'), 'b': ('staf.b', 'B'), 'admin': ('admin', 'Admin')}[who]
        code, result, _ = self.call('/api/login', {'email': account[0] + '@example.test', 'password': 'RuangSurat!2026-' + account[1]})
        assert code == 200, result
        self.csrf = result['csrf']
        return self

    def create(self, title='Pengujian integrasi'):
        code, result, _ = self.call('/api/documents', {'title': title, 'body': 'Data fiktif pengujian otomatis.', 'category': 'Laporan', 'owner_id': 3})
        assert code == 201, result
        return result['id']

    def upload(self, doc_id, name, data, mime='application/octet-stream'):
        boundary = 'RuangSuratTestBoundary'
        body = (f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n').encode() + data + f'\r\n--{boundary}--\r\n'.encode()
        return self.call(f'/api/documents/{doc_id}/attachments', raw=body, content_type=f'multipart/form-data; boundary={boundary}')


class LabTest(unittest.TestCase):
    def test_image_preview(self):
        png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9uoAAAAASUVORK5CYII=')
        for mode, port in [('vulnerable', 8080), ('fixed', 8081)]:
            with self.subTest(profile=mode):
                staff = Client(port).login('a')
                other = Client(port).login('b')
                admin = Client(port).login('admin')
                anon = Client(port)
                doc_id = staff.create('Pengujian pratinjau ' + mode)
                code, image, _ = staff.upload(doc_id, 'gambar.png', png, 'image/png')
                self.assertEqual(code, 201)
                preview = f'/api/attachments/{image["id"]}/preview'
                for client in [staff, admin]:
                    code, body, headers = client.call(preview)
                    self.assertEqual(code, 200)
                    self.assertEqual(body, png)
                    self.assertEqual(headers['Content-Type'], 'image/png')
                    self.assertIn('inline;', headers['Content-Disposition'])
                    self.assertEqual(headers['X-Content-Type-Options'], 'nosniff')
                self.assertEqual(anon.call(preview)[0], 401)
                self.assertEqual(other.call(preview)[0], 404)
                code, body, headers = staff.call(preview.replace('/preview', '/download'))
                self.assertEqual(code, 200)
                self.assertEqual(body, png)
                self.assertIn('attachment;', headers['Content-Disposition'])
                self.assertEqual(staff.call(f'/api/documents/{doc_id}/submit', {})[0], 200)
                self.assertEqual(staff.call(preview)[0], 200)
                # Non-image content must never be served inline, even on the vulnerable profile.
                draft = staff.create('Pengujian tipe pratinjau ' + mode)
                code, text, _ = staff.upload(draft, 'catatan.txt', b'Catatan biasa.', 'text/plain')
                self.assertEqual(code, 201)
                self.assertEqual(staff.call(f'/api/attachments/{text["id"]}/preview')[0], 415)
                for name, data, mime in [
                    ('script.svg', b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml'),
                    ('fake.png', b'not an image', 'image/png'),
                    ('marker.php', Path('poc/marker.php').read_bytes(), 'application/octet-stream'),
                ]:
                    code, uploaded, _ = staff.upload(draft, name, data, mime)
                    self.assertEqual(code, 422 if mode == 'fixed' else 201)
                    if mode == 'vulnerable':
                        self.assertEqual(staff.call(f'/api/attachments/{uploaded["id"]}/preview')[0], 415)

    def test_both_profiles(self):
        for mode, port in [('vulnerable', 8080), ('fixed', 8081)]:
            with self.subTest(profile=mode):
                fixed = mode == 'fixed'
                anon = Client(port)
                self.assertEqual(anon.call('/api/admin/users')[0], 401)
                self.assertEqual(anon.call('/api/login', {'email': "' OR 1=1 --", 'password': 'invalid'})[0], 401)
                staff = Client(port).login('a')
                other = Client(port).login('b')
                admin = Client(port).login('admin')
                self.assertEqual(staff.call('/api/admin/users')[0], 403)
                self.assertEqual(admin.call('/api/admin/users')[0], 200)
                self.assertEqual(staff.call('/admin/users')[0], 403)
                self.assertEqual(anon.call('/api/documents/2')[0], 401)
                code, leaked, _ = staff.call('/api/documents/2')
                self.assertEqual(code, 404 if fixed else 200)
                if not fixed:
                    self.assertEqual(leaked['document']['owner_id'], 2)
                    self.assertEqual(leaked['document']['owner_name'], 'Bima Saputra')
                self.assertEqual(staff.call('/documents/2')[0], 404 if fixed else 200)
                self.assertEqual(other.call('/api/documents/2')[0], 200)
                self.assertEqual(admin.call('/api/documents/2')[0], 200)
                self.assertTrue(all(d['owner_id']==1 for d in staff.call('/api/documents')[1]['documents']))
                # Read-only IDOR: writes and attachment downloads keep ownership checks.
                self.assertEqual(other.call('/api/documents/1', {'title':'Tidak boleh','body':'Tidak boleh'})[0], 404)
                self.assertEqual(other.call('/api/documents/1/links', {'label':'Tidak boleh','url':'https://example.test'})[0], 404)
                self.assertEqual(other.call('/api/documents/1/submit', {})[0], 404)
                self.assertEqual(other.call('/api/documents/1/approve', {})[0], 404)
                self.assertEqual(other.upload(1, 'tidak-boleh.txt', b'Data uji.', 'text/plain')[0], 404)
                self.assertEqual(other.call('/api/attachments/1/download')[0], 404)
                self.assertEqual(staff.call('/api/documents', {'title':'x','body':'x','category':'Laporan'}, csrf=False)[0], 403)
                doc_id = staff.create('Pengujian API ' + mode)
                self.assertEqual(staff.call(f'/api/documents/{doc_id}')[1]['document']['owner_id'], 1)
                self.assertEqual(staff.call(f'/api/documents/{doc_id}', {'title':'Draft diperbarui','body':'Isi baru'})[0], 200)
                self.assertEqual(staff.call(f'/api/documents/{doc_id}/approve', {})[0], 403)
                self.assertEqual(admin.call(f'/api/documents/{doc_id}/approve', {})[0], 409)
                self.assertEqual(staff.call(f'/api/documents/{doc_id}/links', {'label':'Dokumen normal','url':'https://example.test/dokumen'})[0], 201)
                payload = "javascript:alert('RUANGSURAT_XSS')"
                code = staff.call(f'/api/documents/{doc_id}/links', {'label':'Marker XSS','url':payload})[0]
                self.assertEqual(code, 422 if fixed else 201)
                for url in [' JAVASCRIPT:alert(1)', 'data:text/html,test', 'https://user:pass@example.test', 'https://example.test/\nscript']:
                    if fixed:
                        self.assertEqual(staff.call(f'/api/documents/{doc_id}/links', {'label':'Invalid','url':url})[0], 422)
                code, good, _ = staff.upload(doc_id, 'normal.txt', b'Lampiran normal lab.\n', 'text/plain')
                self.assertEqual(code, 201)
                code, body, headers = staff.call('/api/attachments/' + str(good['id']) + '/download')
                self.assertEqual(code, 200)
                self.assertIn(b'Lampiran normal', body)
                self.assertIn('attachment;', headers['Content-Disposition'])
                marker = Path('poc/marker.php').read_bytes()
                code, uploaded, _ = staff.upload(doc_id, 'marker.php', marker)
                self.assertEqual(code, 422 if fixed else 201)
                if not fixed:
                    # This must be interpreter output, not a source echo or mocked response.
                    self.assertEqual(anon.call(uploaded['url'])[1], b'EXECUTED_5')
                    attachments = staff.call(f'/api/documents/{doc_id}')[1]['document']['attachments']
                    php = next(a for a in attachments if a['id'] == uploaded['id'])
                    self.assertEqual(php['execution_url'], uploaded['url'])
                    code, source, headers = staff.call(php['url'])
                    self.assertEqual(code, 200)
                    self.assertEqual(source, marker)
                    self.assertIn('attachment;', headers['Content-Disposition'])
                if fixed:
                    self.assertEqual(staff.upload(doc_id, 'marker.txt', marker, 'text/plain')[0], 422)
                    self.assertEqual(staff.upload(doc_id, 'fake.png', b'not an image', 'image/png')[0], 422)
                    self.assertIn(staff.call('/uploads/marker.php')[0], [403, 404])
                    self.assertIn("frame-ancestors 'none'", staff.call('/')[2]['Content-Security-Policy'])
                self.assertEqual(staff.call(f'/api/documents/{doc_id}/submit', {})[0], 200)
                self.assertEqual(staff.call(f'/api/documents/{doc_id}/links', {'label':'Late','url':'https://example.test'})[0], 403)
                self.assertEqual(admin.call(f'/api/documents/{doc_id}/approve', {})[0], 200)
                self.assertEqual(staff.call(f'/api/documents/{doc_id}')[1]['document']['status'], 'approved')
                rejected = staff.create('Pengujian penolakan')
                self.assertEqual(staff.call(f'/api/documents/{rejected}/submit', {})[0], 200)
                self.assertEqual(admin.call(f'/api/documents/{rejected}/reject', {})[0], 200)
                self.assertEqual(staff.call(f'/api/documents/{rejected}')[1]['document']['status'], 'rejected')
                self.assertEqual(staff.call('/api/logout', {})[0], 200)
                self.assertEqual(staff.call('/api/documents')[0], 401)
                code, public, headers = anon.call('/api/public/info')
                self.assertEqual(code, 200)
                self.assertEqual(headers['Access-Control-Allow-Origin'], '*')
                print(f'PASS {mode}: otorisasi, CSRF, draft, link, upload, keputusan, logout')


if __name__ == '__main__':
    unittest.main(verbosity=2)
