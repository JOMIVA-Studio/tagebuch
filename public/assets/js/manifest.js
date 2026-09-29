(function () {
  const button = document.getElementById('manifestButton');
  if (!button) return;
  const board = document.getElementById('visionBoard');
  const status = document.getElementById('manifestStatus');
  let pending = null;
  const pdfButton = document.getElementById('visionPdfButton');
  async function captureVision(preview = false) {
    if (button.disabled) return;
    const popup = preview ? window.open('about:blank', '_blank') : null;
    if (preview && !popup) {
      status.textContent = 'Bitte erlaube Pop-ups, damit sich deine PDF öffnen kann.';
      return;
    }
    if (popup) {
      popup.opener = null;
      popup.document.title = 'Deine Vision';
      popup.document.body.textContent = 'Deine PDF wird erstellt …';
    }
    button.disabled = true;
    if (pdfButton) pdfButton.disabled = true;
    const wasInert = board.inert;
    board.inert = true;
    status.textContent = 'Deine Vision wird festgehalten …';
    try {
      let snapshot = preview ? null : pending;
      if (!snapshot) {
        await document.fonts.ready;
        await Promise.all(Array.from(board.querySelectorAll('img')).map(async img => {
          await img.decode();
          if (!img.naturalWidth) throw new Error('Ein Bild konnte nicht geladen werden.');
        }));
        // Embed the actual material/image instead of depending on CSS resource loading.
        const computed = getComputedStyle(board);
        let backgroundImage = computed.backgroundImage;
        const urls = [...backgroundImage.matchAll(/url\(["']?(.*?)["']?\)/g)];
        for (const match of urls) {
          const response = await fetch(match[1]);
          if (!response.ok) throw new Error('Der Hintergrund konnte nicht geladen werden.');
          const blob = await response.blob();
          const data = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
          });
          const image = new Image();
          image.src = data;
          await image.decode();
          backgroundImage = backgroundImage.replace(match[0], 'url("' + data + '")');
        }
        const width = board.offsetWidth;
        const height = board.offsetHeight;
        const ratio = Math.min(2, 5000 / width, 5000 / height, Math.sqrt(14000000 / (width * height)));
        const image = await htmlToImage.toJpeg(board, {
          quality: 0.98, pixelRatio: ratio,
          style: {
            backgroundColor: computed.backgroundColor,
            backgroundImage,
            backgroundSize: computed.backgroundSize,
            backgroundPosition: computed.backgroundPosition,
            backgroundRepeat: computed.backgroundRepeat,
          },
          filter: node => !(node.classList && (node.classList.contains('vision-delete-form') || node.classList.contains('vision-text-actions'))),
        });
        const key = Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join('');
        snapshot = { image, key };
        if (!preview) pending = snapshot;
      }
      const form = new FormData();
      form.set('csrf_token', document.querySelector('input[name="csrf_token"]').value);
      form.set('request_key', snapshot.key);
      form.set('image', snapshot.image);
      const response = await fetch('/vision_manifest.php', { method:'POST', body:form });
      const result = await response.json().catch(() => ({ error:'Speichern nicht möglich. Bitte prüfe deine Anmeldung und versuche es erneut.' }));
      if (!response.ok || !result.id) throw new Error(result.error || 'Speichern fehlgeschlagen.');
      if (popup && !popup.closed) popup.location.replace('/vision_file.php?id=' + result.id + '&format=pdf');
      location.href = '/vision.php?saved=' + result.id + '#savedVisions';
    } catch (error) {
      if (popup && !popup.closed) popup.close();
      status.textContent = error.message || 'Die Vision konnte nicht gespeichert werden. Bitte erneut versuchen.';
    } finally {
      button.disabled = false;
      if (pdfButton) pdfButton.disabled = false;
      board.inert = wasInert;
    }
  }
  button.addEventListener('click', () => captureVision(false));
  if (pdfButton) pdfButton.addEventListener('click', () => captureVision(true));
})();
