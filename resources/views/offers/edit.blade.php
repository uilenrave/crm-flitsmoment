@extends('layouts.app')
@section('title', 'Offerte bewerken: ' . $offer->offer_number)

@section('actions')
    <a href="{{ route('offers.show', $offer) }}" class="btn btn-secondary">← Terug</a>
@endsection

@section('content')
<form method="POST" action="{{ route('offers.update', $offer) }}" id="offer-form">
    @csrf
    @method('PUT')

    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.25rem;align-items:start;">

        {{-- Hoofdkolom --}}
        <div>
            {{-- Titel --}}
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-header"><span class="card-title">Offerte details</span></div>
                <div class="form-grid" style="padding:1rem;">
                    <div style="grid-column:span 2;">
                        <label for="title">Titel</label>
                        <input type="text" id="title" name="title" value="{{ old('title', $offer->title) }}" required style="width:100%;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.875rem;">
                    </div>
                    <div>
                        <label for="expires_at">Geldig tot</label>
                        <input type="date" id="expires_at" name="expires_at" value="{{ old('expires_at', $offer->expires_at?->format('Y-m-d')) }}" style="width:100%;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.875rem;">
                    </div>
                </div>
            </div>

            {{-- Line items --}}
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <span class="card-title">Producten & diensten</span>
                    <button type="button" onclick="addRow()" class="btn btn-primary btn-sm">+ Regel toevoegen</button>
                </div>
                <div class="table-wrap">
                    <table id="items-table">
                        <thead>
                            <tr>
                                <th style="width:45%;">Omschrijving</th>
                                <th style="width:12%;text-align:center;">Aantal</th>
                                <th style="width:18%;text-align:right;">Stukprijs</th>
                                <th style="width:18%;text-align:right;">Totaal</th>
                                <th style="width:7%;"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body"></tbody>
                    </table>
                </div>

                <div style="padding:.75rem 1rem;border-top:1px solid #e2e8f0;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                        <span style="font-size:.875rem;color:#64748b;">Subtotaal</span>
                        <span id="subtotal-display" style="font-size:.875rem;font-weight:600;">€ 0,00</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                        <label for="discount-input" style="font-size:.875rem;color:#64748b;">Korting</label>
                        <div style="display:flex;align-items:center;gap:.25rem;">
                            <span style="font-size:.875rem;color:#16a34a;">−€</span>
                            <input type="number" id="discount-input" step="0.01" min="0" value="{{ old('discount', $offer->discount) }}" style="width:100px;padding:.375rem .5rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.875rem;text-align:right;" onchange="recalcTotals()">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:.5rem;border-top:2px solid #1e293b;">
                        <span style="font-size:1rem;font-weight:700;">Totaal</span>
                        <span id="total-display" style="font-size:1.125rem;font-weight:700;">€ 0,00</span>
                    </div>
                </div>
            </div>

            {{-- Voorwaarden --}}
            <div class="card">
                <div class="card-header"><span class="card-title">Voorwaarden</span></div>
                <div style="padding:1rem;">
                    <textarea name="terms_text" rows="8" style="width:100%;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.8rem;resize:vertical;line-height:1.6;">{{ old('terms_text', $offer->terms_text) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Zijbalk --}}
        <div>
            <div class="card" style="margin-bottom:1rem;">
                <div class="card-header"><span class="card-title">Lead</span></div>
                <div style="font-size:.8rem;">
                    <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;">
                        <span style="color:#64748b;">Naam</span>
                        <span>{{ $offer->lead?->name }}</span>
                    </div>
                    <div style="padding:.5rem 0;display:flex;justify-content:space-between;">
                        <span style="color:#64748b;">E-mail</span>
                        <span>{{ $offer->lead?->email ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <input type="hidden" name="line_items" id="line_items_json">
                <input type="hidden" name="subtotal" id="subtotal_input">
                <input type="hidden" name="discount" id="discount_hidden">
                <input type="hidden" name="total" id="total_input">

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;" onclick="return prepareSubmit()">
                    Wijzigingen opslaan
                </button>
                <a href="{{ route('offers.show', $offer) }}" class="btn btn-secondary" style="width:100%;justify-content:center;margin-top:.5rem;">Annuleren</a>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    const initialItems = @json($offer->line_items ?? []);
    let rowIndex = 0;

    function createRow(item = {}) {
        const idx = rowIndex++;
        const desc = item.description || '';
        const qty = item.quantity || 1;
        const price = item.unit_price || 0;
        const total = item.total || 0;

        return `<tr data-row="${idx}">
            <td><input type="text" data-field="description" value="${desc}" style="width:100%;padding:.375rem .5rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.8rem;" placeholder="Omschrijving"></td>
            <td><input type="number" data-field="quantity" value="${qty}" min="1" style="width:100%;padding:.375rem .5rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.8rem;text-align:center;" onchange="recalcRow(this)"></td>
            <td><input type="number" data-field="unit_price" value="${price}" step="0.01" min="0" style="width:100%;padding:.375rem .5rem;border:1px solid #e2e8f0;border-radius:.375rem;font-size:.8rem;text-align:right;" onchange="recalcRow(this)"></td>
            <td style="text-align:right;font-weight:600;font-size:.8rem;" data-field="total-display">€ ${formatNumber(total)}</td>
            <td style="text-align:center;"><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:1rem;" title="Verwijderen">×</button></td>
        </tr>`;
    }

    function addRow(item = {}) {
        document.getElementById('items-body').insertAdjacentHTML('beforeend', createRow(item));
        recalcTotals();
    }

    function removeRow(btn) {
        btn.closest('tr').remove();
        recalcTotals();
    }

    function recalcRow(input) {
        const row = input.closest('tr');
        const qty = parseFloat(row.querySelector('[data-field="quantity"]').value) || 0;
        const price = parseFloat(row.querySelector('[data-field="unit_price"]').value) || 0;
        const total = qty * price;
        row.querySelector('[data-field="total-display"]').textContent = '€ ' + formatNumber(total);
        recalcTotals();
    }

    function recalcTotals() {
        let subtotal = 0;
        document.querySelectorAll('#items-body tr').forEach(row => {
            const qty = parseFloat(row.querySelector('[data-field="quantity"]').value) || 0;
            const price = parseFloat(row.querySelector('[data-field="unit_price"]').value) || 0;
            subtotal += qty * price;
        });

        const discount = parseFloat(document.getElementById('discount-input').value) || 0;
        const total = Math.max(0, subtotal - discount);

        document.getElementById('subtotal-display').textContent = '€ ' + formatNumber(subtotal);
        document.getElementById('total-display').textContent = '€ ' + formatNumber(total);
    }

    function formatNumber(n) {
        return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function getLineItems() {
        const items = [];
        document.querySelectorAll('#items-body tr').forEach(row => {
            const desc = row.querySelector('[data-field="description"]').value.trim();
            const qty = parseFloat(row.querySelector('[data-field="quantity"]').value) || 0;
            const price = parseFloat(row.querySelector('[data-field="unit_price"]').value) || 0;
            if (desc) {
                items.push({ description: desc, quantity: qty, unit_price: price, total: qty * price });
            }
        });
        return items;
    }

    function prepareSubmit() {
        const items = getLineItems();
        if (items.length === 0) { alert('Voeg minimaal één regel toe.'); return false; }

        const subtotal = items.reduce((sum, i) => sum + i.total, 0);
        const discount = parseFloat(document.getElementById('discount-input').value) || 0;
        const total = Math.max(0, subtotal - discount);

        document.getElementById('line_items_json').value = JSON.stringify(items);
        document.getElementById('subtotal_input').value = subtotal.toFixed(2);
        document.getElementById('discount_hidden').value = discount.toFixed(2);
        document.getElementById('total_input').value = total.toFixed(2);
        return true;
    }

    initialItems.forEach(item => addRow(item));
    if (initialItems.length === 0) addRow();
    recalcTotals();
</script>
@endpush
@endsection
