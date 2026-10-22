"""Real-browser checks. Requires Python Playwright and local Chromium."""
import json
import base64
import re
import shutil
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

OUT = Path('archive/evidence/validation')
OUT.mkdir(parents=True, exist_ok=True)


def login(page, base, admin=False):
    page.goto(base + '/login')
    page.locator('[name=email]').fill('admin@example.test' if admin else 'staf.a@example.test')
    page.locator('[name=password]').fill('RuangSurat!2026-Admin' if admin else 'RuangSurat!2026-A')
    page.get_by_role('button', name='Masuk ke ruang kerja').click()
    page.wait_for_url(base + '/')
    expect(page.get_by_role('heading', name='Pengajuan dokumen.')).to_be_visible()


with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=shutil.which('chromium') or shutil.which('chromium-browser'), headless=True, args=['--no-sandbox'])
    errors = []
    for mode, port in [('vulnerable', 8080), ('fixed', 8081)]:
        base = f'http://127.0.0.1:{port}'
        staff = browser.new_context(viewport={'width': 1440, 'height': 1000})
        admin = browser.new_context(viewport={'width': 1440, 'height': 1000})
        page = staff.new_page()
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(base + '/login')
        page.screenshot(path=str(OUT / f'{mode}-login.png'), full_page=True)
        login(page, base)
        page.goto(base + '/documents/1')
        expect(page.get_by_role('heading', name='Pengadaan perangkat ruang rapat')).to_be_visible()
        response = page.goto(base + '/documents/2')
        assert response.status == (200 if mode == 'vulnerable' else 404)
        if mode == 'vulnerable':
            expect(page.get_by_role('heading', name='Surat tugas kunjungan lapangan')).to_be_visible()
            expect(page.locator('.page-heading')).to_contain_text('Bima Saputra')
            expect(page.locator('form[data-action=edit]')).to_have_count(0)
        else:
            expect(page.locator('body')).to_contain_text('Dokumen tidak ditemukan')
        page.screenshot(path=str(OUT / f'LAB-01-{mode}-browser.png'), full_page=True)
        page.goto(base + '/')
        page.screenshot(path=str(OUT / f'{mode}-dashboard.png'), full_page=True)
        page.locator('#document-search').fill('tidak-ada-pengajuan-seperti-ini')
        expect(page.locator('#empty-documents')).to_be_visible()
        page.locator('#document-search').fill('')
        page.goto(base + '/documents/new')
        page.locator('[name=title]').fill('Demo browser ' + mode)
        page.locator('[name=body]').fill('Pengajuan fiktif untuk pembuktian lintas role.')
        page.get_by_role('button', name='Simpan draft').click()
        page.wait_for_url(re.compile(r'/documents/\d+$'))
        doc_url = page.url
        doc_id = int(doc_url.rsplit('/', 1)[1])
        link = page.locator('form[data-action=link]')
        link.locator('[name=label]').fill('Marker lintas role')
        link.locator('[name=url]').fill("javascript:alert('RUANGSURAT_XSS')")
        if mode == 'vulnerable':
            with page.expect_navigation():
                link.get_by_role('button').click()
            expect(page.locator('.reference-link')).to_have_attribute('href', "javascript:alert('RUANGSURAT_XSS')")
        else:
            link.get_by_role('button').click()
            expect(link.locator('.form-message')).to_contain_text('http atau https')
            # Simulate data left over from before a patch via operator-only database access.
            script = '$p=new PDO("sqlite:/var/lib/ruangsurat/lab.sqlite");$s=$p->prepare("UPDATE documents SET links=? WHERE id=?");$s->execute([$argv[1],$argv[2]]);'
            subprocess.run(['docker', 'compose', 'exec', '-T', 'fixed', 'php', '-r', script, '--', json.dumps([{'label':'Legacy marker','url':"javascript:alert('LEGACY')"}]), str(doc_id)], check=True, capture_output=True)
            page.reload()
            expect(page.locator('.blocked-link')).to_contain_text('dinonaktifkan')
            expect(page.locator('a[href^="javascript:"]')).to_have_count(0)
            page.screenshot(path=str(OUT / 'fixed-legacy-link-blocked.png'), full_page=True)
        page.locator('[name=file]').set_input_files({'name':'normal.txt','mimeType':'text/plain','buffer':b'Berkas normal browser.'})
        with page.expect_navigation():
            page.get_by_role('button', name='Unggah berkas').click()
        expect(page.locator('.attachment')).to_contain_text('normal.txt')
        png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9uoAAAAASUVORK5CYII=')
        page.locator('[name=file]').set_input_files({'name':'gambar.png','mimeType':'image/png','buffer':png})
        with page.expect_navigation():
            page.get_by_role('button', name='Unggah berkas').click()
        image = page.locator('.attachment-preview img')
        expect(image).to_be_visible()
        expect(image).to_have_js_property('naturalWidth', 1)
        with page.expect_popup() as popup:
            page.locator('.attachment-preview').click()
        full_image = popup.value
        full_image.wait_for_load_state()
        assert full_image.url.endswith('/preview')
        expect(full_image.locator('img')).to_have_js_property('naturalWidth', 1)
        full_image.close()
        with page.expect_download() as downloaded:
            page.get_by_role('link', name='Unduh gambar').click()
        assert downloaded.value.suggested_filename == 'gambar.png'
        if mode == 'vulnerable':
            page.locator('[name=file]').set_input_files({'name':'marker.php','mimeType':'application/octet-stream','buffer':Path('poc/marker.php').read_bytes()})
            with page.expect_navigation():
                page.get_by_role('button', name='Unggah berkas').click()
            with page.expect_popup() as popup:
                page.get_by_role('link', name=re.compile(r'marker\.php.*Jalankan PHP')).click()
            executed = popup.value
            executed.wait_for_load_state()
            assert '/uploads/' in executed.url
            expect(executed.locator('body')).to_have_text('EXECUTED_5')
            executed.close()
        with page.expect_navigation():
            page.get_by_role('button', name='Kirim pengajuan').click()
        admin_page = admin.new_page()
        admin_page.on('pageerror', lambda error: errors.append(str(error)))
        login(admin_page, base, admin=True)
        admin_page.goto(base + '/admin/users')
        expect(admin_page.locator('#users-table')).to_contain_text('staf.a@example.test')
        admin_page.goto(doc_url)
        expect(admin_page.locator('.attachment-preview img')).to_have_js_property('naturalWidth', 1)
        if mode == 'vulnerable':
            dialogs = []
            def accept_dialog(dialog):
                dialogs.append(dialog.message)
                dialog.accept()
            admin_page.on('dialog', accept_dialog)
            with admin_page.expect_event('dialog'):
                admin_page.get_by_role('link', name='Marker lintas role').click()
            assert dialogs == ['RUANGSURAT_XSS'], dialogs
            (OUT / 'LAB-02-browser.txt').write_text('Staf A menyimpan tautan pada dokumen ' + str(doc_id) + '.\nAdmin membuka tautan; dialog aktual: ' + dialogs[0] + '\n', encoding='utf-8')
        with admin_page.expect_navigation():
            admin_page.get_by_role('button', name='Setujui pengajuan').click()
        expect(admin_page.locator('.page-heading .status')).to_have_text('Disetujui')
        admin_page.screenshot(path=str(OUT / f'{mode}-approved.png'), full_page=True)
        page.goto(base + '/')
        page.set_viewport_size({'width': 390, 'height': 844})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Mobile viewport overflow'
        page.screenshot(path=str(OUT / f'{mode}-mobile.png'), full_page=True)
        page.locator('.mobile-logout').click()
        page.wait_for_url(base + '/login')
        staff.close()
        admin.close()
        print(f'PASS browser {mode}: login, read-only IDOR, draft, upload, XSS/legacy filtering, admin approval, mobile, logout')
    # Cross-origin public CORS and framing controls, without claiming a clickjacking exploit.
    page = browser.new_page()
    page.goto('http://127.0.0.1:8090')
    page.get_by_role('button', name='Baca informasi publik').click()
    expect(page.locator('#output')).to_contain_text('Informasi publik fiktif')
    page.get_by_role('button', name='Muat vulnerable').click()
    expect(page.frame_locator('#frame').get_by_role('heading', name='Selamat datang kembali')).to_be_visible()
    page.get_by_role('button', name='Muat fixed').click()
    page.wait_for_timeout(500)
    assert not page.frame_locator('#frame').get_by_role('heading', name='Selamat datang kembali').count()
    assert not errors, errors
    browser.close()
    print('PASS PoC: public CORS and vulnerable/fixed framing; no JavaScript page errors')
