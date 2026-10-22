/* All server-side authorization remains in PHP; UI visibility is not a security boundary. */
const csrf = document.querySelector('meta[name="csrf-token"]').content;
async function api(path, data, multipart = false) {
  const response = await fetch(path, {
    method: data === undefined ? 'GET' : 'POST',
    headers: data === undefined ? {} : multipart ? {'X-CSRF-Token': csrf} : {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
    body: data === undefined ? undefined : multipart ? data : JSON.stringify(data),
  });
  const result = await response.json();
  if (!response.ok) throw new Error(result.error || 'Permintaan gagal');
  return result;
}
document.querySelectorAll('form[data-action]').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const message = form.querySelector('.form-message');
    const data = Object.fromEntries(new FormData(form));
    message.textContent = ''; button.disabled = true;
    try {
      const action = form.dataset.action;
      if (action === 'login') { await api('/api/login', data); location.assign('/'); }
      else if (action === 'create') { const result = await api('/api/documents', data); location.assign(`/documents/${result.id}`); }
      else {
        const suffix = {edit: '', link: '/links', upload: '/attachments'}[action];
        await api(`/api/documents/${form.dataset.id}${suffix}`, action === 'upload' ? new FormData(form) : data, action === 'upload');
        location.reload();
      }
    } catch (error) { message.textContent = error.message; button.disabled = false; }
  });
});
document.querySelectorAll('[data-action-button]').forEach(button => button.addEventListener('click', async () => {
  button.disabled = true;
  try { await api(`/api/documents/${button.dataset.id}/${button.dataset.actionButton}`, {}); location.reload(); }
  catch (error) { document.querySelector('#global-message').textContent = error.message; button.disabled = false; }
}));
document.querySelectorAll('[data-logout]').forEach(button => button.addEventListener('click', async () => {
  try { await api('/api/logout', {}); location.assign('/login'); }
  catch (error) { document.querySelector('#global-message').textContent = error.message; }
}));
let statusFilter = 'all';
function filterDocuments() {
  const search = (document.querySelector('#document-search')?.value || '').toLocaleLowerCase('id');
  let visible = 0;
  document.querySelectorAll('#document-table tr').forEach(row => {
    const show = (statusFilter === 'all' || row.dataset.status === statusFilter) && row.textContent.toLocaleLowerCase('id').includes(search);
    row.hidden = !show; visible += show ? 1 : 0;
  });
  document.querySelector('#empty-documents')?.classList.toggle('hidden', visible > 0);
}
document.querySelector('#document-search')?.addEventListener('input', filterDocuments);
document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
  statusFilter = button.dataset.filter;
  document.querySelectorAll('[data-filter]').forEach(b => b.classList.toggle('selected', b === button));
  filterDocuments();
}));
if (document.querySelector('#users-table')) {
  api('/api/admin/users').then(({users}) => {
    const body = document.querySelector('#users-table'); body.replaceChildren();
    for (const user of users) {
      const row = document.createElement('tr');
      for (const value of [user.name, user.email, user.role === 'admin' ? 'Administrator' : 'Staf']) {
        const cell = document.createElement('td'); cell.textContent = value; row.append(cell);
      }
      body.append(row);
    }
  }).catch(error => { document.querySelector('#users-table').textContent = error.message; });
}
