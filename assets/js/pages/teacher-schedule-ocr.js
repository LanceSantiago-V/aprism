(() => {
  'use strict';

  const extractor = window.AprismScheduleOcrExtract;
  const dayOptions = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);

  function validFile(file, source) {
    if (!file || file.size < 1 || file.size > 12 * 1024 * 1024) {
      throw new Error('Choose a file smaller than 12 MB.');
    }
    const extension = file.name.split('.').pop().toLowerCase();
    if (source === 'pdf' ? extension !== 'pdf' : !['jpg', 'jpeg', 'png'].includes(extension)) {
      throw new Error(source === 'pdf' ? 'Choose a PDF file.' : 'Choose a JPG or PNG image.');
    }
    return file.slice(0, 8).arrayBuffer().then((buffer) => {
      const bytes = new Uint8Array(buffer);
      const pdf = bytes[0] === 0x25 && bytes[1] === 0x50 && bytes[2] === 0x44 && bytes[3] === 0x46;
      const png = bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47;
      const jpg = bytes[0] === 0xff && bytes[1] === 0xd8;
      if (source === 'pdf' ? !pdf : extension === 'png' ? !png : !jpg) {
        throw new Error('The file contents do not match its selected format.');
      }
    });
  }

  async function imageCanvas(file) {
    let bitmap;
    let objectUrl = '';
    if (typeof createImageBitmap === 'function') {
      bitmap = await createImageBitmap(file);
    } else {
      objectUrl = URL.createObjectURL(file);
      bitmap = new Image();
      bitmap.src = objectUrl;
      await bitmap.decode();
    }
    try {
      const width = bitmap.width || bitmap.naturalWidth;
      const height = bitmap.height || bitmap.naturalHeight;
      const scale = Math.min(4, 2600 / Math.max(width, height));
      const canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(width * scale));
      canvas.height = Math.max(1, Math.round(height * scale));
      const context = canvas.getContext('2d', { willReadFrequently: true });
      context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
      return canvas;
    } finally {
      bitmap.close?.();
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    }
  }

  function prepareCanvas(source) {
    const canvas = document.createElement('canvas');
    canvas.width = source.width;
    canvas.height = source.height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.drawImage(source, 0, 0);
    const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
    for (let index = 0; index < pixels.data.length; index += 4) {
      const gray = pixels.data[index] * 0.299 + pixels.data[index + 1] * 0.587 + pixels.data[index + 2] * 0.114;
      const adjusted = Math.max(0, Math.min(255, (gray - 128) * 1.18 + 128));
      pixels.data[index] = adjusted;
      pixels.data[index + 1] = adjusted;
      pixels.data[index + 2] = adjusted;
    }
    context.putImageData(pixels, 0, 0);
    return canvas;
  }

  function open({ host, source, assetBase, state, onBack, onReview, onValidateBatch, onConfirmBatch, onBatchSaved, academicContext, toast }) {
    let disposed = false;
    let stream = null;
    let worker = null;
    let generation = 0;
    const shell = document.createElement('div');
    shell.className = 'teacher-import-ocr';
    host.prepend(shell);

    const icon = () => window.lucide?.createIcons();
    const message = (text, kind = 'error') => {
      const output = shell.querySelector('[data-ocr-message]');
      if (output) {
        output.hidden = false;
        output.textContent = text;
        output.dataset.kind = kind;
      }
      if (kind === 'error') toast(text, 'error');
    };
    const status = (text) => {
      const output = shell.querySelector('[data-ocr-progress]');
      if (output) output.textContent = text;
    };
    const stopCamera = () => {
      stream?.getTracks().forEach((track) => track.stop());
      stream = null;
      const video = shell.querySelector('video');
      if (video) video.srcObject = null;
    };
    const dispose = () => {
      disposed = true;
      generation += 1;
      stopCamera();
      if (worker) {
        worker.terminate().catch(() => {});
        worker = null;
      }
      shell.remove();
    };

    async function ensureWorker() {
      if (worker) return worker;
      if (!window.Tesseract?.createWorker) throw new Error('The local OCR reader did not load. Refresh and try again.');
      worker = await window.Tesseract.createWorker('eng', 1, {
        workerPath: `${assetBase}/tesseract/worker.min.js`,
        corePath: `${assetBase}/tesseract/core`,
        langPath: `${assetBase}/tesseract/lang`,
        gzip: true,
        logger: (progress) => {
          if (progress.status === 'recognizing text') {
            status(`Reading text ${Math.round((progress.progress || 0) * 100)}%`);
          }
        }
      });
      return worker;
    }

    async function recognize(canvas) {
      const currentWorker = await ensureWorker();
      const result = await currentWorker.recognize(prepareCanvas(canvas), {}, { text: true, tsv: true });
      const text = result?.data?.text || '';
      const words = extractor.wordsFromTsv(result?.data?.tsv || '');
      return { text, words };
    }

    async function readPdf(file) {
      const pdfjs = await import(`${assetBase}/pdfjs/pdf.min.mjs`);
      pdfjs.GlobalWorkerOptions.workerSrc = `${assetBase}/pdfjs/pdf.worker.min.mjs`;
      const pdf = await pdfjs.getDocument({ data: new Uint8Array(await file.arrayBuffer()) }).promise;
      if (pdf.numPages > 5) throw new Error('Review a PDF with five pages or fewer.');
      const results = [];
      for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        status(`Reading PDF page ${pageNumber} of ${pdf.numPages}`);
        const page = await pdf.getPage(pageNumber);
        const content = await page.getTextContent();
        const nativeWords = content.items.filter((item) => item.str?.trim()).map((item, index) => ({
          text: item.str.trim(), x: item.transform[4],
          y: page.view[3] - item.transform[5], width: item.width || 1,
          height: item.height || 10, confidence: 100,
          group: `${pageNumber}:${Math.round((page.view[3] - item.transform[5]) / 7)}`
        }));
        if (nativeWords.map((word) => word.text).join(' ').length >= 24) {
          const lines = new Map();
          for (const word of nativeWords) {
            if (!lines.has(word.group)) lines.set(word.group, []);
            lines.get(word.group).push(word);
          }
          const text = [...lines.values()].sort((a, b) => a[0].y - b[0].y)
            .map((line) => line.sort((a, b) => a.x - b.x).map((word) => word.text).join(' ')).join('\n');
          results.push({ text, words: nativeWords, pageNumber, mode: 'selectable text' });
        } else {
          const viewport = page.getViewport({ scale: Math.min(2.5, 2400 / Math.max(...page.view.slice(2))) });
          const canvas = document.createElement('canvas');
          canvas.width = Math.ceil(viewport.width);
          canvas.height = Math.ceil(viewport.height);
          await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
          results.push({ ...await recognize(canvas), pageNumber, mode: 'scanned page' });
        }
        page.cleanup();
      }
      return results;
    }

    function formatFileSize(bytes) {
      return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function renderSource() {
      const isPdf = source === 'pdf';
      const fileId = 'teacherScheduleOcrFile';
      const accept = isPdf ? '.pdf,application/pdf' : '.jpg,.jpeg,.png,image/jpeg,image/png';
      const fileKind = isPdf ? 'PDF' : 'image';
      shell.innerHTML = `<div class="teacher-import-step-header"><button type="button" class="teacher-import-back-btn" data-ocr-back><i data-lucide="arrow-left"></i><span>Back</span></button><div><h3 class="teacher-import-step-title">${isPdf ? 'PDF schedule' : 'Schedule image'}</h3><p class="teacher-import-step-description">${isPdf ? 'APRISM reads the PDF in this browser.' : 'Choose a timetable image or use a camera.'}</p></div></div>
        <div class="teacher-import-upload-area teacher-import-ocr-upload">
          <input id="${fileId}" class="teacher-import-file-input" data-ocr-file type="file" accept="${accept}">
          <label for="${fileId}" class="teacher-import-dropzone teacher-import-ocr-dropzone" ${state.file ? 'hidden' : ''}>
            <div class="teacher-import-upload-icon"><i data-lucide="${isPdf ? 'file' : 'image'}"></i></div>
            <strong>${isPdf ? 'Choose a PDF' : 'Choose an image'}</strong><span>Select it from this device</span><small>${isPdf ? 'PDF · up to 12 MB · 5 pages or fewer' : 'JPG or PNG · up to 12 MB'}</small>
          </label>
          <div class="teacher-import-selected-file teacher-import-ocr-selected" data-ocr-selected ${state.file ? '' : 'hidden'}>
            <div class="teacher-import-selected-file-icon"><i data-lucide="${isPdf ? 'file' : 'image'}"></i></div>
            <div class="teacher-import-selected-file-info"><strong data-ocr-filename>${escapeHtml(state.file?.name || '')}</strong><span data-ocr-filesize>${state.file ? `${fileKind} · ${formatFileSize(state.file.size)}` : ''}</span></div>
            <label for="${fileId}" class="teacher-import-ocr-replace">Replace file</label>
            <button type="button" class="teacher-import-remove-file" data-ocr-remove aria-label="Remove selected file"><i data-lucide="x"></i></button>
          </div>
        </div>
        ${isPdf ? '' : `<div class="teacher-import-ocr-source"><button type="button" class="teacher-import-ocr-camera-button" data-ocr-camera><i data-lucide="camera"></i><span>Use live camera</span></button><label class="teacher-import-ocr-capture"><i data-lucide="camera"></i><span>Take a photo</span><input data-ocr-capture type="file" accept="image/*" capture="environment"></label></div>`}
        <div class="teacher-import-ocr-camera" data-ocr-camera-view hidden><video autoplay playsinline muted></video><div><button type="button" data-ocr-photo>Capture photo</button><button type="button" class="teacher-import-form-cancel" data-ocr-stop>Stop camera</button></div></div>
        <p class="teacher-import-ocr-message" data-ocr-message role="alert" hidden></p>
        <p class="teacher-import-ocr-local">${isPdf ? 'Text PDFs are read directly. Scanned pages use browser-based OCR.' : 'APRISM reads images in this browser.'} No class is saved until you confirm.</p>
        <div class="teacher-import-form-actions teacher-import-ocr-form-actions"><button type="button" class="teacher-primary-btn" data-ocr-read ${state.file ? '' : 'disabled'}><i data-lucide="arrow-right"></i><span>Read schedule</span></button></div>`;
      icon();
      shell.querySelector('[data-ocr-back]').addEventListener('click', () => { dispose(); onBack(); });
      shell.querySelector('[data-ocr-file]').addEventListener('change', (event) => selectFile(event.target.files?.[0]));
      shell.querySelector('[data-ocr-capture]')?.addEventListener('change', (event) => selectFile(event.target.files?.[0]));
      shell.querySelector('[data-ocr-remove]')?.addEventListener('click', () => {
        state.file = null; state.drafts = []; state.rawText = ''; state.mode = '';
        renderSource();
      });
      shell.querySelector('[data-ocr-read]')?.addEventListener('click', processFile);
      shell.querySelector('[data-ocr-camera]')?.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
          message('Live camera is unavailable here. Choose an image file instead.');
          return;
        }
        try {
          stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
          if (disposed) { stopCamera(); return; }
          const area = shell.querySelector('[data-ocr-camera-view]');
          area.hidden = false;
          area.querySelector('video').srcObject = stream;
        } catch (_) {
          message('Camera access was unavailable. Choose an image file or take a photo with this device.');
        }
      });
      shell.querySelector('[data-ocr-stop]')?.addEventListener('click', () => {
        stopCamera(); shell.querySelector('[data-ocr-camera-view]').hidden = true;
      });
      shell.querySelector('[data-ocr-photo]')?.addEventListener('click', () => {
        const video = shell.querySelector('video');
        if (!video?.videoWidth) { message('Wait for the camera image, then capture the photo.'); return; }
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob((blob) => {
          if (blob) selectFile(new File([blob], 'camera-schedule.jpg', { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.92);
        stopCamera();
      });
    }

    async function selectFile(file) {
      if (!file) return;
      try {
        await validFile(file, source);
        if (disposed) return;
        state.file = file;
        state.drafts = [];
        state.rawText = '';
        state.mode = '';
        renderSource();
      } catch (error) {
        renderSource();
        message(error instanceof Error ? error.message : 'Choose a supported schedule file.');
      }
    }

    function renderProcessing(file) {
      const displayName = escapeHtml(file.name);
      shell.innerHTML = `<div class="teacher-import-ocr-processing"><div class="teacher-import-step-header"><div><h3 class="teacher-import-step-title">Reading schedule</h3><p class="teacher-import-step-description">${displayName}</p></div></div><span class="teacher-import-ocr-spinner" aria-hidden="true"></span><p data-ocr-progress role="status" aria-live="polite">Preparing file…</p><p>Reading stays in this browser. Nothing is saved.</p><button type="button" class="teacher-import-form-cancel" data-ocr-cancel-reading>Cancel reading</button></div>`;
      shell.querySelector('[data-ocr-cancel-reading]').addEventListener('click', () => {
        generation += 1;
        if (worker) { worker.terminate().catch(() => {}); worker = null; }
        renderSource();
      });
    }

    async function processFile() {
      const file = state.file;
      if (!file) return;
      const current = ++generation;
      try {
        renderProcessing(file);
        const results = source === 'pdf' ? await readPdf(file) : [{ ...await recognize(await imageCanvas(file)), pageNumber: 1, mode: 'image OCR' }];
        if (disposed || current !== generation) return;
        state.rawText = results.map((result) => `Page ${result.pageNumber} (${result.mode})\n${result.text}`).join('\n\n');
        state.drafts = extractor.uniqueDrafts(results.flatMap((result) => [
          ...extractor.fromText(result.text), ...extractor.fromGridWords(result.words)
        ]));
        state.mode = results.map((result) => result.mode).join(', ');
        renderDrafts();
      } catch (error) {
        if (disposed || current !== generation) return;
        renderSource();
        message(error instanceof Error ? error.message : 'APRISM could not read this file.');
      } finally {
        if (worker) { await worker.terminate().catch(() => {}); worker = null; }
      }
    }

    function updateReady() {
      const action = shell.querySelector('[data-ocr-review-one]');
      if (!action) return;
      const complete = state.drafts.length > 0 && state.drafts.length <= 20 && state.drafts.every((item) =>
        item.subject && item.section && item.day && item.startTime && item.endTime && item.endTime > item.startTime);
      action.disabled = !complete;
      action.textContent = state.drafts.length > 1 ? `Review ${state.drafts.length} meetings` : 'Continue to class review';
      const note = shell.querySelector('[data-ocr-ready-note]');
      if (note) note.textContent = state.drafts.length > 20
        ? 'Review up to 20 meetings at a time.'
        : !complete ? 'Complete Subject, Section, Day, Start, and End for every meeting.'
          : 'APRISM checks all meetings before anything is saved.';
    }

    function renderBatchConfirmation(result) {
      const groups = new Map();
      for (const item of state.drafts) {
        const key = `${item.subject.trim().toLowerCase()}|${item.section.trim().toLowerCase()}|${item.academicLevel || ''}`;
        if (!groups.has(key)) groups.set(key, { subject: item.subject, section: item.section, academicLevel: item.academicLevel, meetings: [] });
        groups.get(key).meetings.push(item);
      }
      shell.innerHTML = `<div class="teacher-import-step-header"><button type="button" class="teacher-import-back-btn" data-ocr-edit><i data-lucide="arrow-left"></i><span>Back to drafts</span></button><div><h3 class="teacher-import-step-title">Confirm ${state.drafts.length} meetings</h3><p class="teacher-import-step-description">Review the classes and schedules that APRISM will save.</p></div></div>
        <p class="teacher-import-ocr-summary">${escapeHtml(state.file?.name || 'Schedule')} · ${state.drafts.length} meetings · ${Number(result.class_count) || groups.size} ${Number(result.class_count) === 1 || (!result.class_count && groups.size === 1) ? 'class group' : 'class groups'}${academicContext?.schoolYear ? ` · AY ${escapeHtml(academicContext.schoolYear)}` : ''}</p>
        <p class="teacher-import-ocr-message" data-ocr-message role="alert" hidden></p>
        <div class="teacher-import-ocr-groups">${[...groups.values()].map((group) => `<section class="teacher-import-ocr-group"><h4>${escapeHtml(group.subject)} · ${escapeHtml(group.section)}${group.academicLevel ? ` · ${escapeHtml(group.academicLevel)}` : ''}</h4><ul>${group.meetings.map((item) => `<li>${escapeHtml(item.day)} · ${escapeHtml(item.startTime)}–${escapeHtml(item.endTime)}${item.room ? ` · ${escapeHtml(item.room)}` : ''}</li>`).join('')}</ul></section>`).join('')}</div>
        <p class="teacher-import-ocr-transaction-note">APRISM checks every meeting again before saving. If one fails, none are saved.</p>
        <div class="teacher-import-ocr-actions"><p>Nothing has been saved yet.</p><button type="button" class="teacher-primary-btn" data-ocr-confirm-batch>Confirm ${state.drafts.length} meetings</button></div>`;
      icon();
      shell.querySelector('[data-ocr-edit]').addEventListener('click', renderDrafts);
      shell.querySelector('[data-ocr-confirm-batch]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        button.textContent = 'Saving meetings…';
        try {
          const saved = await onConfirmBatch(state.drafts.map((item) => ({ ...item })), state.file);
          if (!saved?.success) throw new Error(saved?.message || 'The meetings could not be saved.');
          toast(saved.message || 'Meetings saved to My Classes.', 'success');
          dispose();
          onBatchSaved();
        } catch (error) {
          button.disabled = false;
          button.textContent = `Confirm ${state.drafts.length} meetings`;
          message(error instanceof Error ? error.message : 'The meetings could not be saved.');
        }
      });
    }

    async function validateBatch() {
      const action = shell.querySelector('[data-ocr-review-one]');
      action.disabled = true;
      action.textContent = 'Checking meetings…';
      try {
        const result = await onValidateBatch(state.drafts.map((item) => ({ ...item })), state.file);
        if (!result?.success) throw new Error(result?.message || 'The meetings need correction.');
        renderBatchConfirmation(result);
      } catch (error) {
        message(error instanceof Error ? error.message : 'APRISM could not check the meetings.');
        updateReady();
      }
    }

    function renderDrafts() {
      shell.innerHTML = `<div class="teacher-import-step-header"><button type="button" class="teacher-import-back-btn" data-ocr-new><i data-lucide="arrow-left"></i><span>Choose another file</span></button><div><h3 class="teacher-import-step-title">Review detected meetings</h3><p class="teacher-import-step-description">Check every field against the timetable. Unclear values are left empty.</p></div></div>
        <p class="teacher-import-ocr-summary">${escapeHtml(state.file?.name || 'Schedule')} · ${state.drafts.length} ${state.drafts.length === 1 ? 'draft' : 'drafts'} · ${escapeHtml(state.mode || '')}</p>
        <p class="teacher-import-ocr-message" data-ocr-message role="alert" hidden></p>
        ${state.drafts.length ? `<div class="teacher-import-ocr-drafts">${state.drafts.map((item, index) => `<fieldset class="teacher-import-ocr-draft" data-ocr-draft="${index}"><legend>Meeting ${index + 1}${item.uncertain ? ' · needs review' : ''}</legend>
          <div class="teacher-import-ocr-fields"><label>Subject<input data-ocr-field="subject" value="${escapeHtml(item.subject)}" placeholder="From timetable"></label><label>Section<input data-ocr-field="section" value="${escapeHtml(item.section)}" placeholder="From timetable"></label>
          <label>Academic Level<select data-ocr-field="academicLevel"><option value="">Use linked Section level</option><option value="College" ${item.academicLevel === 'College' ? 'selected' : ''}>College</option><option value="Senior High School" ${item.academicLevel === 'Senior High School' ? 'selected' : ''}>Senior High School</option></select></label>
          <label>Day<select data-ocr-field="day"><option value="">Choose day</option>${dayOptions.map((day) => `<option value="${day}" ${item.day === day ? 'selected' : ''}>${day}</option>`).join('')}</select></label>
          <label>Start<input type="time" data-ocr-field="startTime" value="${escapeHtml(item.startTime)}"></label><label>End<input type="time" data-ocr-field="endTime" value="${escapeHtml(item.endTime)}"></label><label>Room<input data-ocr-field="room" value="${escapeHtml(item.room)}" placeholder="Optional"></label></div>
          <div class="teacher-import-ocr-draft-foot"><small>${escapeHtml(item.evidence ? `Detected: ${item.evidence.slice(0, 180)}` : 'Added for manual review')}</small><button type="button" data-ocr-remove="${index}">Remove</button></div></fieldset>`).join('')}</div>` : '<p class="teacher-import-ocr-empty">No reliable meeting rows were found. Add a draft and copy the details from the source.</p>'}
        <button type="button" class="teacher-import-ocr-add" data-ocr-add>Add meeting draft</button>
        <details class="teacher-import-ocr-text"><summary>View extracted text</summary><pre>${escapeHtml((state.rawText || 'No readable text found.').slice(0, 12000))}</pre></details>
        <div class="teacher-import-ocr-actions"><p data-ocr-ready-note></p><button type="button" class="teacher-primary-btn" data-ocr-review-one>Continue to class review</button></div>`;
      icon();
      shell.querySelector('[data-ocr-new]').addEventListener('click', () => { state.file = null; state.drafts = []; state.rawText = ''; renderSource(); });
      shell.querySelector('[data-ocr-add]').addEventListener('click', () => { state.drafts.push(extractor.draft({})); renderDrafts(); });
      shell.querySelectorAll('[data-ocr-remove]').forEach((button) => button.addEventListener('click', () => {
        state.drafts.splice(Number(button.dataset.ocrRemove), 1); renderDrafts();
      }));
      shell.querySelectorAll('[data-ocr-field]').forEach((field) => field.addEventListener('input', () => {
        const index = Number(field.closest('[data-ocr-draft]').dataset.ocrDraft);
        state.drafts[index][field.dataset.ocrField] = field.value.trim();
        updateReady();
      }));
      shell.querySelector('[data-ocr-review-one]').addEventListener('click', () => {
        updateReady();
        if (shell.querySelector('[data-ocr-review-one]').disabled) return;
        if (state.drafts.length > 1) { validateBatch(); return; }
        const item = { ...state.drafts[0] };
        dispose();
        onReview(item, state.file);
      });
      updateReady();
    }

    if (state.file && Array.isArray(state.drafts)) renderDrafts();
    else renderSource();
    return { dispose };
  }

  window.AprismScheduleOcr = { open };
})();
