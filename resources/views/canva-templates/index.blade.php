@extends('layouts.app')

@section('title', 'Canva templates')

@section('content')

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem;">
    <div>
        <h2 style="margin:0;font-size:1.25rem;font-weight:700;color:#1e293b;">🎨 Canva templates</h2>
        <p style="margin:.2rem 0 0;font-size:.85rem;color:#64748b;">Galerij met Canva-templates die klanten kunnen openen vanuit het portaal.</p>
    </div>
    <a href="{{ route('canva-templates.create') }}" class="btn btn-primary">+ Template toevoegen</a>
</div>

@if($templates->isEmpty())
<div class="card neu-card" style="text-align:center;padding:2.5rem 1.5rem;">
    <p style="font-size:1.5rem;margin-bottom:.5rem;">📭</p>
    <p style="font-weight:600;margin-bottom:.5rem;">Nog geen Canva-templates</p>
    <p style="color:#64748b;font-size:.875rem;margin-bottom:1.25rem;">Voeg je eerste template toe — titel, Canva-link en thumbnail.</p>
    <a href="{{ route('canva-templates.create') }}" class="btn btn-primary">+ Eerste template toevoegen</a>
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">
    @foreach($templates as $t)
    <div class="card" style="padding:0;overflow:hidden;{{ $t->is_active ? '' : 'opacity:.55;' }}">
        <div style="position:relative;aspect-ratio:1/1;background:#f8fafc;display:flex;align-items:center;justify-content:center;padding:.4rem;">
            @if($t->image_path)
                <img src="{{ $t->image_url }}" alt="{{ $t->title }}" style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;">
            @else
                <span style="font-size:2rem;color:#cbd5e1;">🖼</span>
            @endif
            @if(!$t->is_active)
            <div style="position:absolute;top:.5rem;right:.5rem;background:#fee2e2;color:#991b1b;padding:.15rem .5rem;border-radius:.4rem;font-size:.7rem;font-weight:700;">Inactief</div>
            @endif
        </div>
        <div style="padding:.75rem 1rem;">
            <div style="font-weight:600;font-size:.9rem;color:#1e293b;margin-bottom:.35rem;line-height:1.3;">{{ $t->title }}</div>
            <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.5rem;">
                <span style="font-size:.7rem;background:#dbeafe;color:#1e40af;padding:.1rem .45rem;border-radius:999px;font-weight:600;">{{ $t->format_label }}</span>
                <span style="font-size:.7rem;background:#fef3c7;color:#92400e;padding:.1rem .45rem;border-radius:999px;font-weight:600;">📷 {{ $t->photo_count }} {{ $t->photo_count === 1 ? 'foto' : 'foto\'s' }}</span>
            </div>
            <div style="display:flex;gap:.4rem;align-items:center;">
                <a href="{{ $t->canva_url }}" target="_blank" rel="noopener" title="Open in Canva"
                   style="padding:.4rem .55rem;background:#fff;border:1px solid #c4b5fd;color:#6d28d9;border-radius:.4rem;font-size:.8rem;font-weight:600;text-decoration:none;">↗</a>
                <a href="{{ route('canva-templates.edit', $t) }}" class="btn btn-secondary btn-sm" style="flex:1;text-align:center;">Bewerken</a>
                <form method="POST" action="{{ route('canva-templates.destroy', $t) }}" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="button" onclick="crmConfirm('Template &quot;{{ addslashes($t->title) }}&quot; verwijderen?', () => this.closest('form').submit()); return false;"
                            style="padding:.4rem .6rem;background:none;border:1px solid #fca5a5;color:#dc2626;border-radius:.4rem;cursor:pointer;font-size:.8rem;">🗑</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
