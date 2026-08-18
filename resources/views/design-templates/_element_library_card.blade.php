{{-- Eén goedgekeurd element in de bibliotheek: transparante PNG + hercategoriseren + verwijderen. --}}
<div class="dt-card">
    <div class="dt-thumb dt-thumb-element"><img src="{{ $e->url }}" loading="lazy" alt=""></div>
    <div class="dt-card-body">
        @if($e->label)<div style="font-size:.76rem;font-weight:600;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $e->label }}</div>@endif
        @if($e->usage_count > 0)<span class="dt-badge">🔁 {{ $e->usage_count }}× gebruikt</span>@endif
        <form method="POST" action="{{ route('design-elements.recategorize', $e) }}" style="display:flex;gap:.3rem;">
            @csrf
            <select name="category_id" class="dt-select" onchange="this.form.submit()">
                <option value="">— zonder —</option>
                @foreach($elementCategories as $c)
                    <option value="{{ $c->id }}" @selected($e->category_id === $c->id)>{{ $c->label }}</option>
                @endforeach
            </select>
        </form>
        <form method="POST" action="{{ route('design-elements.destroy', $e) }}">
            @csrf @method('DELETE')
            <button type="button" class="dt-btn dt-btn-reject" style="width:100%;"
                    onclick="crmConfirm('Dit element verwijderen uit de bibliotheek?', () => this.closest('form').submit()); return false;">🗑 Verwijderen</button>
        </form>
    </div>
</div>
