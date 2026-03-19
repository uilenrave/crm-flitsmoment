<form method="POST" action="{{ route('portal.strip-input', $token) }}" enctype="multipart/form-data">
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
            <div id="strip-input-filenames" style="margin-top:.5rem;font-size:.78rem;color:var(--primary);font-weight:600;"></div>
        </div>
        <input type="file" id="strip-input-files" name="strip_input_files[]" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" multiple style="display:none;"
               onchange="showStripInputFiles(this)">
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
function handleStripInputDrop(event) {
    event.preventDefault();
    const zone = document.getElementById('strip-input-dropzone');
    zone.style.borderColor = '#cbd5e1';
    zone.style.background  = '#f8fafc';
    const dt = new DataTransfer();
    Array.from(event.dataTransfer.files).forEach(f => dt.items.add(f));
    const input = document.getElementById('strip-input-files');
    input.files = dt.files;
    showStripInputFiles(input);
}

function showStripInputFiles(input) {
    const label = document.getElementById('strip-input-filenames');
    if (input.files && input.files.length > 0) {
        label.textContent = '✓ ' + Array.from(input.files).map(f => f.name).join(', ');
    }
}
</script>
