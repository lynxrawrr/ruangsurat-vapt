document.querySelectorAll('[data-frame]').forEach(button => button.addEventListener('click', () => {
  document.querySelector('#frame').src = `http://127.0.0.1:${button.dataset.frame}/`;
}));
document.querySelector('#cors').addEventListener('click', async () => {
  try {
    const result = await fetch('http://127.0.0.1:8080/api/public/info', {credentials: 'omit'});
    document.querySelector('#output').textContent = JSON.stringify(await result.json(), null, 2);
  } catch (error) { document.querySelector('#output').textContent = error.message; }
});
