{{-- Eén goedgekeurde template in de bibliotheek: afbeelding + hercategoriseren + verwijderen. --}}
<div class="dt-card">
    <div class="dt-thumb"><img src="{{ $t->url }}" loading="lazy" alt=""></div>
    <div class="dt-card-body">
        @if($t->label)<div style="font-size:.76rem;font-weight:600;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $t->label }}</div>@endif
        @if($t->usage_count > 0)<span class="dt-badge">🔁 {{ $t->usage_count }}× gebruikt</span>@endif
        <form method="POST" action="{{ route('design-templates.recategorize', $t) }}" style="display:flex;gap:.3rem;">
            @csrf
            <select name="category_id" class="dt-select" onchange="this.form.submit()">
                <option value="">— zonder —</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected($t->category_id === $c->id)>{{ $c->label }}</option>
                @endforeach
            </select>
        </form>
        <form method="POST" action="{{ route('design-templates.destroy', $t) }}">
            @csrf @method('DELETE')
            <button type="button" class="dt-btn dt-btn-reject" style="width:100%;"
                    onclick="crmConfirm('Deze template verwijderen uit de bibliotheek?', () => this.closest('form').submit()); return false;">🗑 Verwijderen</button>
        </form>
    </div>
</div>
