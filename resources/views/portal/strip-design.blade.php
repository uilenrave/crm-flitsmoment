@extends('layouts.portal')
@section('title', 'Uw fotostrip ontwerp')

@section('content')
<div style="max-width:600px;margin:0 auto;padding:2rem 1.5rem;">

    {{-- Header --}}
    <div style="margin-bottom:2rem;">
        <h1 style="font-size:1.875rem;font-weight:bold;margin:0 0 0.5rem 0;">📸 Uw Fotostrip Ontwerp</h1>
        <p style="margin:0;color:#64748b;">Boeking {{ $booking->booking_number }}</p>
    </div>

    @if(session('success'))
        <div style="padding:1rem;background:#dcfce7;color:#16a34a;border-radius:0.5rem;margin-bottom:1.5rem;border-left:4px solid #16a34a;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Current Design Section --}}
    <div class="card" style="margin-bottom:2rem;padding:1.5rem;">
        <h2 style="font-size:1.25rem;font-weight:600;margin:0 0 1rem 0;">Huidig Ontwerp</h2>

        {{-- Design Image --}}
        @if($booking->strip_design_url)
            <div style="margin-bottom:1.5rem;">
                <img src="{{ $booking->strip_design_url }}" alt="Strip Design" style="width:100%;border-radius:0.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
            </div>

            {{-- Status Badge --}}
            <div style="margin-bottom:1.5rem;">
                @php
                    $statusColors = [
                        'waiting' => ['bg' => '#f3f4f6', 'color' => '#6b7280', 'label' => '⏳ Wachten op input'],
                        'in_progress' => ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => '🎨 Wordt gemaakt'],
                        'review' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'label' => '👀 Ter beoordeling'],
                        'accepted' => ['bg' => '#dcfce7', 'color' => '#16a34a', 'label' => '✅ Goedgekeurd'],
                        'done' => ['bg' => '#e9d5ff', 'color' => '#7e22ce', 'label' => '🎉 Ingezet bij event'],
                    ];
                    $status = $statusColors[$booking->strip_status] ?? $statusColors['waiting'];
                @endphp
                <span style="display:inline-block;padding:0.5rem 1rem;background:{{ $status['bg'] }};color:{{ $status['color'] }};border-radius:9999px;font-weight:600;font-size:0.875rem;">
                    {{ $status['label'] }}
                </span>
            </div>

            {{-- Design Info --}}
            <div style="padding:1rem;background:#f9fafb;border-radius:0.375rem;margin-bottom:1.5rem;border-left:4px solid #fcd34d;">
                <div style="font-size:0.875rem;color:#64748b;margin-bottom:0.5rem;">
                    Versie: <strong>{{ $booking->strip_version ?? 1 }}</strong>
                </div>
                @if($booking->strip_feedback_at)
                    <div style="font-size:0.875rem;color:#64748b;">
                        Feedback gegeven: <strong>{{ $booking->strip_feedback_at->format('d M Y \o\m H:i') }}</strong>
                    </div>
                @endif
            </div>

        @else
            <div style="padding:2rem;text-align:center;background:#f9fafb;border-radius:0.5rem;color:#9ca3af;">
                <p>⏳ Het ontwerp wordt nog voorbereid. Kom straks terug!</p>
            </div>
        @endif

    </div>

    {{-- Feedback Section (if design is ready for review) --}}
    @if($booking->strip_design_url && in_array($booking->strip_status, ['review', 'accepted']))
        <div class="card" style="padding:1.5rem;">
            <h2 style="font-size:1.25rem;font-weight:600;margin:0 0 1rem 0;">
                @if($booking->strip_status === 'review')
                    💬 Uw Feedback
                @else
                    ✅ U hebt goedgekeurd
                @endif
            </h2>

            {{-- Display existing feedback --}}
            @if($booking->strip_feedback)
                <div style="padding:1rem;background:#f9fafb;border-radius:0.5rem;margin-bottom:1.5rem;border-left:4px solid #16a34a;">
                    <div style="margin-bottom:0.75rem;">
                        <p style="margin:0;color:#111827;">{{ nl2br(e($booking->strip_feedback)) }}</p>
                    </div>
                    <div style="font-size:0.75rem;color:#64748b;">
                        Feedback gegeven op {{ $booking->strip_feedback_at->format('d M Y \o\m H:i') }}
                    </div>
                </div>

                {{-- Action buttons after feedback --}}
                @if($booking->strip_status === 'review')
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                        <form method="POST" action="{{ route('portal.strip-review', $booking->public_token) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="btn btn-primary">✅ Goedkeuren</button>
                        </form>
                        <a href="{{ route('portal.show', $booking->public_token) }}" class="btn btn-secondary">← Terug naar boeking</a>
                    </div>
                @else
                    <a href="{{ route('portal.show', $booking->public_token) }}" class="btn btn-secondary">← Terug naar boeking</a>
                @endif

            {{-- Feedback form if not yet given --}}
            @elseif($booking->strip_status === 'review')
                <form method="POST" action="{{ route('portal.strip-feedback', $booking->public_token) }}">
                    @csrf

                    <div style="margin-bottom:1rem;">
                        <label style="display:block;margin-bottom:0.5rem;font-weight:600;color:#111827;">
                            Uw Feedback:
                        </label>
                        <textarea name="feedback"
                                  placeholder="Wat vindt u van het ontwerp? Voeg suggesties in voor aanpassingen..."
                                  style="width:100%;padding:0.75rem;border:1px solid #d1d5db;border-radius:0.375rem;font-family:inherit;font-size:0.875rem;resize:vertical;"
                                  rows="5"
                                  required></textarea>
                    </div>

                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary">💬 Feedback Verzenden</button>
                        <a href="{{ route('portal.show', $booking->public_token) }}" class="btn btn-secondary">← Terug naar boeking</a>
                    </div>
                </form>
            @else
                <a href="{{ route('portal.show', $booking->public_token) }}" class="btn btn-secondary">← Terug naar boeking</a>
            @endif

        </div>
    @else
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <a href="{{ route('portal.show', $booking->public_token) }}" class="btn btn-secondary">← Terug naar boeking</a>
        </div>
    @endif

</div>
@endsection
