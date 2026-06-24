<form id="strip-input-form" method="POST" action="{{ route('portal.strip-input', $token) }}" enctype="multipart/form-data">
    @csrf

    <div style="margin-bottom:1rem;">
        <label style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.4rem;">Jouw wensen en omschrijving</label>
        <textarea name="strip_input_text"
                  placeholder="Bijv: Thema: Tropical, namen: Lisa & Tom, kleur: roze/goud, graag een ananas in de hoek..."
                  style="width:100%;padding:.75rem;border:1px solid var(--border);border-radius:.5rem;font-family:inherit;font-size:.875rem;resize:vertical;min-height:110px;"
                  >{{ old('strip_input_text') }}</textarea>
    </div>

    <div style="margin-bottom:1.25rem;">
        <label style="display:block;font-weight:600;font-size:.875rem;margin-bottom:.4rem;">Referentieafbeeldingen of logo's (optioneel, max 5)</label>

        {{-- Drop zone --}}
        <div id="strip-input-dropzone"
             style="border:2px dashed #cbd5e1;border-radius:10px;padding:1.25rem 1rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;background:#f8fafc;"
             onclick="document.getElementById('strip-input-files').click()"
             ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)';"
             ondragleave="this.style.borderColor='#cbd5e1';this.style.background='#f8fafc';"
             ondrop="handleStripInputDrop(event)">
            <div style="font-size:1.5rem;margin-bottom:.3rem;">📎</div>
            <div style="font-size:.8rem;font-weight:600;color:#374151;">Sleep bestanden hierheen of klik om te kiezen</div>
            <div style="font-size:.72rem;color:#94a3b8;margin-top:.2rem;">JPG, PNG, PDF — max 10 MB per bestand</div>
        </div>
        {{-- Hidden file input (wordt via JS gevuld) --}}
        <input type="file" id="strip-input-files" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" multiple style="display:none;">

        {{-- Geselecteerde bestanden --}}
        <div id="strip-input-filelist" style="margin-top:.5rem;display:flex;flex-direction:column;gap:.3rem;"></div>
    </div>

    @error('strip_input_text')
        <div style="color:#dc2626;font-size:.8rem;margin-bottom:.75rem;">{{ $message }}</div>
    @enderror
    @error('strip_input_files')
        <div style="color:#dc2626;font-size:.8rem;margin-bottom:.75rem;">{{ $message }}</div>
    @enderror

    <button type="submit" style="background:var(--primary);color:#fff;border:none;padding:.75rem 1.75rem;border-radius:.5rem;font-size:.9rem;font-weight:700;cursor:pointer;width:100%;">
        ✅ Input versturen
    </button>
</form>

<script>
(function () {
    const MAX_FILES = 5;
    const MAX_MB    = 10;
    let selectedFiles = [];

    const fileInput = document.getElementById('strip-input-files');
    const dropZone  = document.getElementById('strip-input-dropzone');
    const listEl    = document.getElementById('strip-input-filelist');
    const form      = document.getElementById('strip-input-form');

    // Voeg bestanden toe aan de selectie (duplicaten worden overgeslagen)
    function addFiles(files) {
        for (const f of files) {
            if (selectedFiles.length >= MAX_FILES) break;
            if (f.size > MAX_MB * 1024 * 1024) continue;
            if (selectedFiles.some(s => s.name === f.name && s.size === f.size)) continue;
            selectedFiles.push(f);
        }
        renderList();
    }

    function removeFile(idx) {
        selectedFiles.splice(idx, 1);
        renderList();
    }

    function renderList() {
        listEl.innerHTML = selectedFiles.map((f, i) => `
            <div style="display:flex;align-items:center;justify-content:space-between;padding:.35rem .6rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:.8rem;color:#1d4ed8;">
                <span>📄 ${f.name} <span style="color:#64748b;">(${(f.size/1024/1024).toFixed(1)} MB)</span></span>
                <button type="button" onclick="stripRemoveFile(${i})" style="background:none;border:none;cursor:pointer;color:#64748b;font-size:.9rem;padding:0 .2rem;">✕</button>
            </div>
        `).join('');
    }

    // Globaal zodat onclick het kan aanroepen
    window.stripRemoveFile = removeFile;

    // File picker
    fileInput.addEventListener('change', () => {
        addFiles([...fileInput.files]);
        fileInput.value = '';
    });

    // Drag & drop
    dropZone.addEventListener('drop', (ev) => {
        ev.preventDefault();
        dropZone.style.borderColor = '#cbd5e1';
        dropZone.style.background  = '#f8fafc';
        addFiles([...ev.dataTransfer.files]);
    });

    // Form submit: voeg bestanden toe aan FormData
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        const fd = new FormData(form);
        selectedFiles.forEach(f => fd.append('strip_input_files[]', f));
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': fd.get('_token'), 'Accept': 'application/json, text/html, */*' },
            body: fd,
        }).then(res => {
            // Redirect on success (server returns redirect response)
            if (res.redirected) { window.location.href = res.url; return; }
            if (res.ok) { window.location.reload(); } else { res.text().then(t => console.error(t)); }
        });
    });
})();

// Drag-style state (buiten de closure want ondragover/ondragleave zijn inline)
window.handleStripInputDrop = function() {}; // placeholder om JS-fout bij oude drop handler te voorkomen
</script>
