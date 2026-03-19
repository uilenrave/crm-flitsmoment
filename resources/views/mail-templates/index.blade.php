@extends('layouts.app')
@section('title', 'Mailtemplates')

@section('content')

{{-- ── Status banner ── --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.25rem;">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:.5rem;padding:1rem;display:flex;align-items:flex-start;gap:.75rem;">
        <div style="font-size:1.25rem;line-height:1;">⚡</div>
        <div>
            <div style="font-weight:600;font-size:.875rem;margin-bottom:.25rem;">Direct actief (8 triggers)</div>
            <div style="font-size:.8rem;color:#64748b;">Vuren automatisch bij acties in het CRM of portaal: nieuwe boeking, gallerij, ontwerp upload, betaling, feedback.</div>
        </div>
    </div>
    <div style="background:#fff;border:1px solid #fde68a;border-radius:.5rem;padding:1rem;display:flex;align-items:flex-start;gap:.75rem;">
        <div style="font-size:1.25rem;line-height:1;">⏰</div>
        <div>
            <div style="font-weight:600;font-size:.875rem;margin-bottom:.25rem;">Geplande taken (3 triggers)</div>
            <div style="font-size:.8rem;color:#64748b;">
                Vereist een dagelijkse cron. Lokaal testen:
                <code style="background:#fef9c3;padding:.1rem .3rem;border-radius:.25rem;font-size:.75rem;">php artisan schedule:work</code>
                <br>Productie (Hostinger hPanel → Cron Jobs):
                <code style="background:#fef9c3;padding:.1rem .3rem;border-radius:.25rem;font-size:.75rem;">* * * * * php /home/.../artisan schedule:run</code>
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:1.25rem;align-items:start;">

{{-- ── Templates tabel ── --}}
<div>
    <div class="card" style="margin-bottom:1.25rem;">
        @if(session('success'))
            <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
        @endif

        @foreach(['customer' => ['label' => '📧 Klantmails', 'color' => '#2563eb', 'icon' => '⚡'], 'admin' => ['label' => '🔧 Adminmails (naar jou)', 'color' => '#7c3aed', 'icon' => '⚡']] as $type => $info)
        @php $groep = $templates->where('recipient_type', $type); @endphp
        @if($groep->isNotEmpty())
        <div style="margin-bottom:2rem;">
            <h3 style="font-size:.8rem;font-weight:700;color:{{ $info['color'] }};text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem;padding-bottom:.5rem;border-bottom:2px solid {{ $info['color'] }}20;">
                {{ $info['label'] }}
            </h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Naam</th>
                            <th>Wanneer</th>
                            <th style="text-align:center;">Actief</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groep as $template)
                        @php
                            $isCron = in_array($template->key, ['customer_photo_upsell', 'admin_strip_reminder', 'admin_event_finished']);
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight:600;font-size:.875rem;">{{ $template->name }}</div>
                                <div style="font-size:.75rem;color:#64748b;max-width:260px;margin-top:.1rem;">{{ Str::limit($template->subject, 55) }}</div>
                            </td>
                            <td>
                                <span style="font-size:.75rem;background:{{ $isCron ? '#fef9c3' : '#f0fdf4' }};color:{{ $isCron ? '#92400e' : '#166534' }};padding:.2rem .5rem;border-radius:9999px;font-weight:600;">
                                    {{ $isCron ? '⏰ Cron' : '⚡ Direct' }}
                                </span>
                                <div style="font-size:.75rem;color:#64748b;margin-top:.25rem;max-width:200px;">{{ $template->description }}</div>
                            </td>
                            <td style="text-align:center;">
                                @if($template->is_active)
                                    <span class="badge" style="background:#dcfce7;color:#16a34a;">Aan</span>
                                @else
                                    <span class="badge" style="background:#fee2e2;color:#dc2626;">Uit</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('mail-templates.edit', $template) }}" class="btn btn-sm btn-secondary">Bewerken</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endforeach
    </div>

    {{-- ── Recente verzonden mails ── --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">📬 Verzonden mails (log)</span>
        </div>
        @forelse($recentLogs as $log)
        <div style="padding:.625rem 0;border-bottom:1px solid #f1f5f9;display:grid;grid-template-columns:1fr auto;gap:.5rem;align-items:start;">
            <div>
                <div style="font-size:.8rem;font-weight:600;">{{ $log->subject }}</div>
                <div style="font-size:.75rem;color:#64748b;margin-top:.1rem;">
                    → {{ $log->recipient_email }}
                    @if($log->booking)
                        · <a href="{{ route('bookings.show', $log->booking_id) }}" style="color:#2563eb;">{{ $log->booking->booking_number }}</a>
                    @endif
                    · <code style="font-size:.7rem;background:#f1f5f9;padding:.1rem .3rem;border-radius:.2rem;">{{ $log->template_key }}</code>
                </div>
            </div>
            <div style="text-align:right;white-space:nowrap;">
                @if($log->status === 'sent')
                    <span style="font-size:.75rem;color:#16a34a;font-weight:600;">✓ Verstuurd</span>
                @else
                    <span style="font-size:.75rem;color:#dc2626;font-weight:600;">✗ Mislukt</span>
                @endif
                <div style="font-size:.7rem;color:#94a3b8;">{{ $log->created_at->diffForHumans() }}</div>
            </div>
        </div>
        @empty
        <p style="color:#64748b;font-size:.875rem;">Nog geen mails verstuurd.</p>
        @endforelse
    </div>
</div>

{{-- ── Sidebar: variabelen + info ── --}}
<div>
    <div class="card">
        <div class="card-header"><span class="card-title">Beschikbare variabelen</span></div>
        <div style="font-size:.8rem;">
            @foreach([
                '{{klant_naam}}'        => 'Volledige naam',
                '{{klant_voornaam}}'    => 'Eerste naam',
                '{{klant_email}}'       => 'E-mailadres klant',
                '{{boeking_nummer}}'    => 'Boekingsnummer',
                '{{event_datum}}'       => 'Datum uitgeschreven',
                '{{event_locatie}}'     => 'Locatienaam',
                '{{event_adres}}'       => 'Volledig adres',
                '{{totaal_prijs}}'      => 'Totaalbedrag',
                '{{openstaand_bedrag}}' => 'Nog te betalen',
                '{{portal_link}}'       => 'URL klantportaal',
                '{{gallery_link}}'      => 'URL fotogalerij',
                '{{ontwerp_link}}'      => 'URL fotostrip ontwerp',
                '{{opmerkingen_klant}}' => 'Feedback klant',
                '{{bedrijf_naam}}'      => 'Naam bedrijf',
            ] as $var => $desc)
            <div style="padding:.4rem 0;border-bottom:1px solid #f8fafc;display:flex;justify-content:space-between;gap:.5rem;align-items:baseline;">
                <code style="font-size:.72rem;color:#2563eb;background:#f0f9ff;padding:.1rem .3rem;border-radius:.2rem;white-space:nowrap;">{{ $var }}</code>
                <span style="color:#64748b;font-size:.75rem;text-align:right;">{{ $desc }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <div class="card" style="margin-top:1rem;">
        <div class="card-header"><span class="card-title">Mail instelling</span></div>
        <div style="font-size:.8rem;">
            @php
                $mailer = config('mail.default');
                $from   = config('mail.from.address');
            @endphp
            <div style="padding:.4rem 0;border-bottom:1px solid #f8fafc;display:flex;justify-content:space-between;">
                <span style="color:#64748b;">Driver</span>
                <span style="font-weight:600;">
                    @if($mailer === 'log')
                        <span style="color:#d97706;">log (lokaal)</span>
                    @else
                        <span style="color:#16a34a;">{{ $mailer }}</span>
                    @endif
                </span>
            </div>
            <div style="padding:.4rem 0;border-bottom:1px solid #f8fafc;display:flex;justify-content:space-between;">
                <span style="color:#64748b;">Van</span>
                <span>{{ $from }}</span>
            </div>
            <div style="padding:.4rem 0;display:flex;justify-content:space-between;">
                <span style="color:#64748b;">Admin e-mail</span>
                <span>{{ auth()->user()->account->email ?? '—' }}</span>
            </div>
            @if($mailer === 'log')
            <div style="margin-top:.75rem;padding:.625rem;background:#fffbeb;border:1px solid #fde68a;border-radius:.375rem;font-size:.75rem;color:#92400e;">
                Lokale mails verschijnen in <code>storage/logs/laravel.log</code>. Zet <code>MAIL_MAILER=resend</code> voor productie.
            </div>
            @endif
        </div>
    </div>
</div>

</div>
@endsection
