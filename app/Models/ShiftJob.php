<?php

namespace App\Models;

use App\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftJob extends Model
{
    protected $fillable = [
        'shift_id', 'booking_id', 'action', 'sort_order', 'duration_minutes',
    ];

    protected $casts = [
        'sort_order'       => 'integer',
        'duration_minutes' => 'integer',
    ];

    /**
     * De vier klus-types. Per actie: label/icoon, basis-werktijd (min), boekingstype,
     * het tijdveld op de boeking (vereiste tijd), de locatie (event = eventadres, depot =
     * startlocatie van de shift) en de legacy-rol voor de oude staff-koppeling.
     */
    public const ACTIONS = [
        'opbouwen' => ['label' => 'Opbouwen', 'icon' => '🚚', 'default_minutes' => 45, 'booking_type' => 'full_service', 'time_field' => 'delivery_at',        'location' => 'event', 'legacy' => 'delivery'],
        'afbouwen' => ['label' => 'Afbouwen', 'icon' => '🧹', 'default_minutes' => 15, 'booking_type' => 'full_service', 'time_field' => 'pickup_at',          'location' => 'event', 'legacy' => 'pickup'],
        'afgeven'  => ['label' => 'Afgeven',  'icon' => '📦', 'default_minutes' => 30, 'booking_type' => 'to_go',        'time_field' => 'customer_pickup_at', 'location' => 'depot', 'legacy' => 'delivery'],
        'aannemen' => ['label' => 'Aannemen', 'icon' => '↩️', 'default_minutes' => 15, 'booking_type' => 'to_go',        'time_field' => 'customer_return_at', 'location' => 'depot', 'legacy' => 'pickup'],
        // Losse klus zonder boeking: laden op het depot (startlocatie)
        'laden'    => ['label' => 'Laden',    'icon' => '📥', 'default_minutes' => 15, 'booking_type' => null,           'time_field' => null,                 'location' => 'depot', 'legacy' => null],
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScope(AccountScope::class);
    }

    public function meta(): array
    {
        return self::ACTIONS[$this->action] ?? self::ACTIONS['opbouwen'];
    }

    public function label(): string
    {
        return $this->meta()['label'];
    }

    public function icon(): string
    {
        return $this->meta()['icon'];
    }

    /** De vereiste tijd van dit moment (delivery_at / pickup_at / customer_pickup_at / customer_return_at). */
    public function requiredAt(): ?\Illuminate\Support\Carbon
    {
        $field = $this->meta()['time_field'];
        if (! $field || ! $this->booking) {
            return null;   // bv. "Laden" heeft geen vaste tijd
        }

        return $this->booking->{$field};
    }

    /** Adres-string voor routing: eventadres (FS) of de depot/startlocatie van de shift (To Go). */
    public function locationAddress(): ?string
    {
        if ($this->meta()['location'] === 'depot') {
            return $this->shift?->startLocation();
        }

        $b = $this->booking;
        if (! $b) {
            return null;
        }

        return collect([$b->event_address, $b->event_postcode, $b->event_city])
            ->filter()->implode(', ') ?: $b->event_location;
    }

    /** Korte omschrijving van de locatie voor de UI. */
    public function locationLabel(): string
    {
        if ($this->meta()['location'] === 'depot') {
            return 'Depot';
        }

        $b = $this->booking;
        return $b?->event_city ?: ($b?->event_location ?: 'Onbekend');
    }

    /** Legacy staff-kolom die bij deze actie hoort, of null (bv. "Laden" koppelt geen boeking). */
    public function legacyStaffColumn(): ?string
    {
        $legacy = $this->meta()['legacy'];
        if (! $legacy || ! $this->booking) {
            return null;
        }

        return $legacy === 'pickup' ? 'pickup_staff_id' : 'delivery_staff_id';
    }

    /** Zet de shift-medewerker(s) op de oude staff-velden van de boeking (bridge, incl. 2e persoon). */
    public function syncLegacyStaff(): void
    {
        if ($col = $this->legacyStaffColumn()) {
            $this->booking->update([
                $col          => $this->shift?->staff_id,
                $col . '_2'   => $this->shift?->staff_id_2,
            ]);
        }
    }

    /** Maak de oude staff-velden van de boeking weer leeg (bij losmaken). */
    public function clearLegacyStaff(): void
    {
        if ($col = $this->legacyStaffColumn()) {
            $this->booking->update([$col => null, $col . '_2' => null]);
        }
    }
}
