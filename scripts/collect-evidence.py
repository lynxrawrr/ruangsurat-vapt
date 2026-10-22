"""Collect redacted API evidence from the local lab; creates dummy drafts."""
import json
import sys
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'tests'))
from test_lab import Client

out = Path(__file__).resolve().parents[1] / 'archive' / 'evidence' / 'validation'
out.mkdir(parents=True, exist_ok=True)

for mode, port in [('vulnerable', 8080), ('fixed', 8081)]:
    client = Client(port).login('a')
    session = client.call('/api/session')[1]
    own = client.call('/api/documents/1')
    other = client.call('/api/documents/2')
    doc = client.create('Evidence ' + mode)
    payload = {'label': 'Marker lintas role', 'url': "javascript:alert('RUANGSURAT_XSS')"}
    link = client.call(f'/api/documents/{doc}/links', payload)
    upload = client.upload(doc, 'marker.php', (Path(__file__).resolve().parents[1] / 'poc/marker.php').read_bytes())
    marker = None
    if mode == 'vulnerable':
        response = client.call(upload[1]['url'])
        marker = {'status': response[0], 'body': response[1].decode()}
    records = {
        'LAB-01': {'method': 'GET', 'path': '/api/documents/2', 'browser_path': '/documents/2', 'changed_identifier': '1 -> 2', 'own_document': {'path': '/api/documents/1', 'status': own[0], 'owner_id': own[1]['document']['owner_id']}, 'status': other[0], 'response': other[1]},
        'LAB-02': {'method': 'POST', 'path': f'/api/documents/{doc}/links', 'request_body': payload, 'status': link[0], 'response': link[1]},
        'LAB-03': {'method': 'POST', 'path': f'/api/documents/{doc}/attachments', 'file': 'poc/marker.php', 'status': upload[0], 'response': upload[1], 'marker_request': marker},
    }
    for finding, record in records.items():
        record.update(time=datetime.now(ZoneInfo('Asia/Jakarta')).isoformat(), profile=mode, build=session['build'], role='Staf A', authentication='Cookie: [REDACTED]; X-CSRF-Token: [REDACTED]')
        (out / f'{finding}-{mode}.json').write_text(json.dumps(record, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    client.call('/api/logout', {})
    print('Evidence teredaksi tersimpan:', mode)
