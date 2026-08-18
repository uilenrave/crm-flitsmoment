@extends('layouts.app')

@section('title', 'Ontwerp-bibliotheek')

@php
    $sourceLabels = [
        'disk_import'     => '📦 Import',
        'admin_generator' => '⚙️ Admin',
        'customer_portal' => '👤 Klant',
        'admin_upload'    => '⬆️ Upload',
    ];
@endphp

@section('content')
<style>
    .dt-boardtabs { display:flex; gap:.5rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .dt-boardtab { padding:.6rem 1.1rem; border:1px solid #e2e8f0; background:#fff; border-radius:.6rem; font-size:.9rem; font-weight:700; color:#475569; cursor:pointer; }
    .dt-boardtab.active { background:#1e293b; border-color:#1e293b; color:#fff; }
    .dt-board { display:none; }
    .dt-board.active { display:block; }
    .dt-tabs { display:flex; gap:.5rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .dt-tab { padding:.5rem .9rem; border:1px solid #e2e8f0; background:#fff; border-radius:.6rem; font-size:.85rem; font-weight:600; color:#475569; cursor:pointer; }
    .dt-tab.active { background:#7c3aed; border-color:#7c3aed; color:#fff; }
    .dt-section { display:none; }
    .dt-section.active { display:block; }
    .dt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:1rem; }
    .dt-card { border:1px solid #e2e8f0; border-radius:.7rem; overflow:hidden; background:#fff; display:flex; flex-direction:column; }
    .dt-thumb { aspect-ratio:1/3; background:#f8fafc; overflow:hidden; }
    .dt-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    .dt-thumb-element { aspect-ratio:1/1; background:#fff repeating-conic-gradient(#eef2f7 0% 25%, #fff 0% 50%) 50% / 16px 16px; }
    .dt-thumb-element img { object-fit:contain; padding:6px; }
    .dt-card-body { padding:.55rem .65rem; display:flex; flex-direction:column; gap:.4rem; }
    .dt-badge { font-size:.68rem; font-weight:700; padding:.1rem .45rem; border-radius:999px; background:#f1f5f9; color:#475569; align-self:flex-start; }
    .dt-select, .dt-input { width:100%; padding:.35rem .45rem; border:1px solid #e2e8f0; border-radius:.4rem; font-size:.78rem; box-sizing:border-box; }
    .dt-btn { padding:.35rem .5rem; border-radius:.4rem; font-size:.76rem; font-weight:700; cursor:pointer; border:1px solid transparent; text-align:center; }
    .dt-btn-approve { background:#dcfce7; color:#15803d; border-color:#86efac; }
    .dt-btn-reject { background:#fff; color:#dc2626; border-color:#fca5a5; }
    .dt-cat-head { font-size:1rem; font-weight:700; color:#1e293b; margin:1.5rem 0 .75rem; display:flex; align-items:center; gap:.5rem; }
    .dt-panel { background:#fff; border:1px solid #e2e8f0; border-radius:.75rem; padding:1.1rem 1.25rem; margin-bottom:1.25rem; }
</style>

<div style="margin-bottom:1rem;">
    <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">🖼️ Ontwerp-bibliotheek</h2>
    <p style="margin:.2rem 0 0;font-size:.85rem;color:#64748b;">Keur gegenereerde achtergronden en vrijgestelde elementen goed en beheer de bibliotheken die in de generator gekozen kunnen worden.</p>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

<div class="dt-boardtabs">
    <button type="button" class="dt-boardtab active" data-board="bg" onclick="dtBoard('bg')">🖼️ Achtergronden</button>
    <button type="button" class="dt-boardtab" data-board="el" onclick="dtBoard('el')">🧩 Elementen ({{ $pendingElements->count() }} in wachtrij)</button>
</div>

{{-- ══════════════════════ ACHTERGRONDEN ══════════════════════ --}}
<div class="dt-board active" id="board-bg">
    <div class="dt-tabs">
        <button type="button" class="dt-tab active" data-tab="queue" onclick="dtTab('bg','queue')">📥 Wachtrij ({{ $pending->count() }})</button>
        <button type="button" class="dt-tab" data-tab="library" onclick="dtTab('bg','library')">🗂️ Bibliotheek</button>
        <button type="button" class="dt-tab" data-tab="categories" onclick="dtTab('bg','categories')">🏷️ Categorieën</button>
    </div>

    {{-- WACHTRIJ --}}
    <div class="dt-section active" id="dt-bg-queue">
        @if($pending->isEmpty())
            <div class="dt-panel" style="text-align:center;color:#64748b;">📭 Geen achtergronden in de wachtrij. Gegenereerde achtergronden verschijnen hier automatisch.</div>
        @else
            <div class="dt-grid">
                @foreach($pending as $t)
                <div class="dt-card">
                    <div class="dt-thumb"><img src="{{ $t->url }}" loading="lazy" alt=""></div>
                    <div class="dt-card-body">
                        <span class="dt-badge">{{ $sourceLabels[$t->source] ?? $t->source }}@if($t->sourceBooking) · {{ $t->sourceBooking->booking_number }}@endif</span>
                        <form method="POST" action="{{ route('design-templates.approve', $t) }}" style="display:flex;flex-direction:column;gap:.35rem;">
                            @csrf
                            <select name="category_id" class="dt-select">
                                <option value="">— kies categorie —</option>
                                @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->label }}</option>@endforeach
                            </select>
                            <input type="text" name="new_category" class="dt-input" placeholder="of nieuwe categorie…">
                            <button type="submit" class="dt-btn dt-btn-approve">✓ Goedkeuren</button>
                        </form>
                        <form method="POST" action="{{ route('design-templates.reject', $t) }}">
                            @csrf
                            <button type="button" class="dt-btn dt-btn-reject" style="width:100%;"
                                    onclick="crmConfirm('Deze achtergrond afwijzen en verwijderen?', () => this.closest('form').submit()); return false;">✕ Afwijzen</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- BIBLIOTHEEK --}}
    <div class="dt-section" id="dt-bg-library">
        <div class="dt-panel">
            <div style="font-weight:700;color:#1e293b;margin-bottom:.6rem;">⬆️ Eigen achtergrond toevoegen</div>
            <form method="POST" action="{{ route('design-templates.upload') }}" enctype="multipart/form-data" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;">
                @csrf
                <div style="flex:1;min-width:180px;">
                    <label style="font-size:.75rem;font-weight:600;color:#475569;">Afbeelding (wordt bijgesneden op 2:6)</label>
                    <input type="file" name="image" accept="image/*" required class="dt-input">
                </div>
                <div style="min-width:160px;">
                    <label style="font-size:.75rem;font-weight:600;color:#475569;">Categorie</label>
                    <select name="category_id" class="dt-select">
                        <option value="">— zonder categorie —</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->label }}</option>@endforeach
                    </select>
                </div>
                <div style="min-width:160px;"><input type="text" name="new_category" class="dt-input" placeholder="of nieuwe categorie…"></div>
                <button type="submit" class="btn btn-primary">Toevoegen</button>
            </form>
        </div>

        @php $anyApproved = false; @endphp
        @foreach($categories as $cat)
            @php $items = $approved[$cat->id] ?? collect(); @endphp
            @if($items->isNotEmpty())
                @php $anyApproved = true; @endphp
                <div class="dt-cat-head">{{ $cat->label }} <span style="font-size:.8rem;color:#94a3b8;font-weight:500;">({{ $items->count() }})</span></div>
                <div class="dt-grid">
                    @foreach($items as $t)@include('design-templates._library_card', ['t' => $t, 'categories' => $categories])@endforeach
                </div>
            @endif
        @endforeach

        @php $uncat = $approved[null] ?? ($approved->get('') ?? collect()); @endphp
        @if($uncat->isNotEmpty())
            @php $anyApproved = true; @endphp
            <div class="dt-cat-head">Zonder categorie <span style="font-size:.8rem;color:#94a3b8;font-weight:500;">({{ $uncat->count() }})</span></div>
            <div class="dt-grid">
                @foreach($uncat as $t)@include('design-templates._library_card', ['t' => $t, 'categories' => $categories])@endforeach
            </div>
        @endif

        @unless($anyApproved)
            <div class="dt-panel" style="text-align:center;color:#64748b;">Nog geen goedgekeurde achtergronden in de bibliotheek.</div>
        @endunless
    </div>

    {{-- CATEGORIEËN --}}
    <div class="dt-section" id="dt-bg-categories">
        <div class="dt-panel">
            <div style="font-weight:700;color:#1e293b;margin-bottom:.6rem;">➕ Nieuwe categorie</div>
            <form method="POST" action="{{ route('design-templates.categories.store') }}" style="display:flex;gap:.6rem;">
                @csrf
                <input type="text" name="label" class="dt-input" placeholder="Naam categorie" required style="max-width:280px;">
                <button type="submit" class="btn btn-primary">Toevoegen</button>
            </form>
        </div>
        <div class="dt-panel">
            @if($categories->isEmpty())
                <p style="color:#64748b;">Nog geen categorieën.</p>
            @else
            <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
                <thead><tr style="text-align:left;color:#64748b;font-size:.75rem;text-transform:uppercase;">
                    <th style="padding:.5rem;">Categorie</th><th style="padding:.5rem;">Achtergronden</th><th style="padding:.5rem;text-align:right;">Acties</th>
                </tr></thead>
                <tbody>
                @foreach($categories as $cat)
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:.5rem;">
                        <form method="POST" action="{{ route('design-templates.categories.update', $cat) }}" style="display:flex;gap:.4rem;align-items:center;">
                            @csrf @method('PATCH')
                            <input type="text" name="label" value="{{ $cat->label }}" class="dt-input" style="max-width:220px;">
                            <button type="submit" class="dt-btn" style="background:#f1f5f9;color:#334155;">Opslaan</button>
                        </form>
                    </td>
                    <td style="padding:.5rem;color:#64748b;">{{ $cat->approved_count }}</td>
                    <td style="padding:.5rem;text-align:right;">
                        <form method="POST" action="{{ route('design-templates.categories.destroy', $cat) }}" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="button" class="dt-btn dt-btn-reject"
                                    onclick="crmConfirm('Categorie &quot;{{ addslashes($cat->label) }}&quot; verwijderen? De achtergronden blijven bestaan maar staan dan zonder categorie.', () => this.closest('form').submit()); return false;">🗑 Verwijderen</button>
                        </form>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

{{-- ══════════════════════ ELEMENTEN ══════════════════════ --}}
<div class="dt-board" id="board-el">
    <div class="dt-tabs">
        <button type="button" class="dt-tab active" data-tab="queue" onclick="dtTab('el','queue')">📥 Wachtrij ({{ $pendingElements->count() }})</button>
        <button type="button" class="dt-tab" data-tab="library" onclick="dtTab('el','library')">🗂️ Bibliotheek</button>
        <button type="button" class="dt-tab" data-tab="categories" onclick="dtTab('el','categories')">🏷️ Categorieën</button>
    </div>

    {{-- WACHTRIJ --}}
    <div class="dt-section active" id="dt-el-queue">
        @if($pendingElements->isEmpty())
            <div class="dt-panel" style="text-align:center;color:#64748b;">📭 Geen elementen in de wachtrij. Vrijgestelde elementen verschijnen hier automatisch.</div>
        @else
            <div class="dt-grid">
                @foreach($pendingElements as $e)
                <div class="dt-card">
                    <div class="dt-thumb dt-thumb-element"><img src="{{ $e->url }}" loading="lazy" alt=""></div>
                    <div class="dt-card-body">
                        <span class="dt-badge">{{ $sourceLabels[$e->source] ?? $e->source }}@if($e->sourceBooking) · {{ $e->sourceBooking->booking_number }}@endif</span>
                        <form method="POST" action="{{ route('design-elements.approve', $e) }}" style="display:flex;flex-direction:column;gap:.35rem;">
                            @csrf
                            <select name="category_id" class="dt-select">
                                <option value="">— kies categorie —</option>
                                @foreach($elementCategories as $c)<option value="{{ $c->id }}">{{ $c->label }}</option>@endforeach
                            </select>
                            <input type="text" name="new_category" class="dt-input" placeholder="of nieuwe categorie…">
                            <button type="submit" class="dt-btn dt-btn-approve">✓ Goedkeuren</button>
                        </form>
                        <form method="POST" action="{{ route('design-elements.reject', $e) }}">
                            @csrf
                            <button type="button" class="dt-btn dt-btn-reject" style="width:100%;"
                                    onclick="crmConfirm('Dit element afwijzen en verwijderen?', () => this.closest('form').submit()); return false;">✕ Afwijzen</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- BIBLIOTHEEK --}}
    <div class="dt-section" id="dt-el-library">
        <div class="dt-panel">
            <div style="font-weight:700;color:#1e293b;margin-bottom:.6rem;">⬆️ Eigen element toevoegen</div>
            <form method="POST" action="{{ route('design-elements.upload') }}" enctype="multipart/form-data" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;">
                @csrf
                <div style="flex:1;min-width:180px;">
                    <label style="font-size:.75rem;font-weight:600;color:#475569;">Afbeelding (transparante PNG aanbevolen)</label>
                    <input type="file" name="image" accept="image/*" required class="dt-input">
                </div>
                <div style="min-width:160px;">
                    <label style="font-size:.75rem;font-weight:600;color:#475569;">Categorie</label>
                    <select name="category_id" class="dt-select">
                        <option value="">— zonder categorie —</option>
                        @foreach($elementCategories as $c)<option value="{{ $c->id }}">{{ $c->label }}</option>@endforeach
                    </select>
                </div>
                <div style="min-width:160px;"><input type="text" name="new_category" class="dt-input" placeholder="of nieuwe categorie…"></div>
                <button type="submit" class="btn btn-primary">Toevoegen</button>
            </form>
        </div>

        @php $anyApprovedEl = false; @endphp
        @foreach($elementCategories as $cat)
            @php $items = $approvedElements[$cat->id] ?? collect(); @endphp
            @if($items->isNotEmpty())
                @php $anyApprovedEl = true; @endphp
                <div class="dt-cat-head">{{ $cat->label }} <span style="font-size:.8rem;color:#94a3b8;font-weight:500;">({{ $items->count() }})</span></div>
                <div class="dt-grid">
                    @foreach($items as $e)@include('design-templates._element_library_card', ['e' => $e, 'elementCategories' => $elementCategories])@endforeach
                </div>
            @endif
        @endforeach

        @php $uncatEl = $approvedElements[null] ?? ($approvedElements->get('') ?? collect()); @endphp
        @if($uncatEl->isNotEmpty())
            @php $anyApprovedEl = true; @endphp
            <div class="dt-cat-head">Zonder categorie <span style="font-size:.8rem;color:#94a3b8;font-weight:500;">({{ $uncatEl->count() }})</span></div>
            <div class="dt-grid">
                @foreach($uncatEl as $e)@include('design-templates._element_library_card', ['e' => $e, 'elementCategories' => $elementCategories])@endforeach
            </div>
        @endif

        @unless($anyApprovedEl)
            <div class="dt-panel" style="text-align:center;color:#64748b;">Nog geen goedgekeurde elementen in de bibliotheek.</div>
        @endunless
    </div>

    {{-- CATEGORIEËN --}}
    <div class="dt-section" id="dt-el-categories">
        <div class="dt-panel">
            <div style="font-weight:700;color:#1e293b;margin-bottom:.6rem;">➕ Nieuwe categorie</div>
            <form method="POST" action="{{ route('design-elements.categories.store') }}" style="display:flex;gap:.6rem;">
                @csrf
                <input type="text" name="label" class="dt-input" placeholder="Naam categorie" required style="max-width:280px;">
                <button type="submit" class="btn btn-primary">Toevoegen</button>
            </form>
        </div>
        <div class="dt-panel">
            @if($elementCategories->isEmpty())
                <p style="color:#64748b;">Nog geen categorieën.</p>
            @else
            <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
                <thead><tr style="text-align:left;color:#64748b;font-size:.75rem;text-transform:uppercase;">
                    <th style="padding:.5rem;">Categorie</th><th style="padding:.5rem;">Elementen</th><th style="padding:.5rem;text-align:right;">Acties</th>
                </tr></thead>
                <tbody>
                @foreach($elementCategories as $cat)
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:.5rem;">
                        <form method="POST" action="{{ route('design-elements.categories.update', $cat) }}" style="display:flex;gap:.4rem;align-items:center;">
                            @csrf @method('PATCH')
                            <input type="text" name="label" value="{{ $cat->label }}" class="dt-input" style="max-width:220px;">
                            <button type="submit" class="dt-btn" style="background:#f1f5f9;color:#334155;">Opslaan</button>
                        </form>
                    </td>
                    <td style="padding:.5rem;color:#64748b;">{{ $cat->approved_count }}</td>
                    <td style="padding:.5rem;text-align:right;">
                        <form method="POST" action="{{ route('design-elements.categories.destroy', $cat) }}" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="button" class="dt-btn dt-btn-reject"
                                    onclick="crmConfirm('Categorie &quot;{{ addslashes($cat->label) }}&quot; verwijderen? De elementen blijven bestaan maar staan dan zonder categorie.', () => this.closest('form').submit()); return false;">🗑 Verwijderen</button>
                        </form>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>

<script>
function dtBoard(board) {
    document.querySelectorAll('.dt-boardtab').forEach(b => b.classList.toggle('active', b.dataset.board === board));
    document.querySelectorAll('.dt-board').forEach(b => b.classList.remove('active'));
    document.getElementById('board-' + board).classList.add('active');
}
function dtTab(board, name) {
    const root = document.getElementById('board-' + board);
    root.querySelectorAll('.dt-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    root.querySelectorAll('.dt-section').forEach(s => s.classList.remove('active'));
    document.getElementById('dt-' + board + '-' + name).classList.add('active');
}
</script>
@endsection
