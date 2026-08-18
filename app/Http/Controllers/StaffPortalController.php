<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\RideSignup;
use App\Models\Staff;
use App\Models\StaffHours;
use App\Services\MailService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffPortalController extends Controller
{
    /**
     * Zoek de medewerker bij het portaal-token. Inactieve medewerkers worden geblokkeerd (403):
     * ze mogen via hun oude link geen planning/beschikbaarheid/uren meer zien of muteren.
     */
    private function resolveStaff(string $token): Staff
    {
        $staff = Staff::withoutGlobalScopes()
            ->where('public_token', $token)
            ->firstOrFail();

        abort_if(! $staff->is_active, 403, 'Deze medewerkerspagina is niet meer actief. Neem contact op als dit onverwacht is.');

        return $staff;
    }

    public function show(Request $request, string $token): View
    {
        $staff = $this->resolveStaff($token);

        $now = Carbon::now()->startOfDay();
        $tab = $request->input('tab', 'aankomend');

        // ── Aankomende ritten ─────────────────────────────────────────────
        $deliveryBookings = $staff->deliveryBookings()
            ->withoutGlobalScopes()
            ->with(['items.asset'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($now) {
                $q->where('delivery_at', '>=', $now)
                  ->orWhere(function ($q2) use ($now) {
                      $q2->whereNull('delivery_at')
                         ->where('event_date', '>=', $now->toDateString());
                  });
            })
            ->orderBy('delivery_at')
            ->orderBy('event_date')
            ->get();

        $pickupBookings = $staff->pickupBookings()
            ->withoutGlobalScopes()
            ->with(['items.asset'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($now) {
                $q->where('pickup_at', '>=', $now)
                  ->orWhere('customer_return_at', '>=', $now)
                  ->orWhere(function ($q2) use ($now) {
                      $q2->whereNull('pickup_at')->whereNull('customer_return_at')
                         ->where('event_date', '>=', $now->toDateString());
                  });
            })
            ->orderBy('pickup_at')
            ->orderBy('event_date')
            ->get();

        // ── Archief: afgesloten ritten ────────────────────────────────────
        $pastDeliveryBookings = $staff->deliveryBookings()
            ->withoutGlobalScopes()
            ->with(['items.asset'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($now) {
                $q->where('delivery_at', '<', $now)
                  ->orWhere(function ($q2) use ($now) {
                      $q2->whereNull('delivery_at')
                         ->where('event_date', '<', $now->toDateString());
                  });
            })
            ->orderByDesc('event_date')
            ->get();

        $pastPickupBookings = $staff->pickupBookings()
            ->withoutGlobalScopes()
            ->with(['items.asset'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($now) {
                $q->where('pickup_at', '<', $now)
                  ->orWhere('customer_return_at', '<', $now)
                  ->orWhere(function ($q2) use ($now) {
                      $q2->whereNull('pickup_at')->whereNull('customer_return_at')
                         ->where('event_date', '<', $now->toDateString());
                  });
            })
            ->orderByDesc('event_date')
            ->get();

        // ── Preload ureninzendingen voor alle boekingen ───────────────────
        $allBookingIds = $deliveryBookings->pluck('id')
            ->merge($pickupBookings->pluck('id'))
            ->merge($pastDeliveryBookings->pluck('id'))
            ->merge($pastPickupBookings->pluck('id'))
            ->unique();

        $existingHours = StaffHours::withoutGlobalScopes()
            ->with('combinedWithBooking')
            ->where('staff_id', $staff->id)
            ->whereIn('booking_id', $allBookingIds)
            ->get()
            ->keyBy(fn($h) => $h->booking_id . '|' . $h->role);

        // Verzamel alle (booking, role) tuples waar deze medewerker op staat — voor de combinatie-dropdown
        $allStaffRitten = collect();
        foreach ($deliveryBookings->concat($pastDeliveryBookings) as $b) {
            $role = $b->booking_type === 'to_go' ? 'handover' : 'delivery';
            $datum = match($role) {
                'handover' => $b->customer_pickup_at ?: $b->event_date,
                default    => $b->delivery_at ?: $b->event_date,
            };
            $allStaffRitten->push(['booking' => $b, 'role' => $role, 'datum' => $datum]);
        }
        foreach ($pickupBookings->concat($pastPickupBookings) as $b) {
            $datum = $b->booking_type === 'to_go'
                ? ($b->customer_return_at ?: $b->event_date)
                : ($b->pickup_at ?: $b->event_date);
            $allStaffRitten->push(['booking' => $b, 'role' => 'pickup', 'datum' => $datum]);
        }

        // ── Beschikbare ritten (open voor aanmelding) ─────────────────────
        $openBookings = Booking::withoutGlobalScopes()
            ->where('account_id', $staff->account_id)
            ->whereIn('status', ['confirmed'])
            ->where('event_date', '>=', $now->toDateString())
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('delivery_open', true)->whereNull('delivery_staff_id');
                })->orWhere(function ($q2) {
                    $q2->where('pickup_open', true)->whereNull('pickup_staff_id');
                });
            })
            ->with(['items.asset'])
            ->orderBy('event_date')
            ->get();

        // Splits naar losse ritten; alleen de zijde die daadwerkelijk openstaat
        $availableRitten = collect();
        foreach ($openBookings as $b) {
            if ($b->delivery_open && ! $b->delivery_staff_id) {
                $role = $b->booking_type === 'to_go' ? 'handover' : 'delivery';
                $datum = $b->booking_type === 'to_go'
                    ? ($b->customer_pickup_at ? Carbon::parse($b->customer_pickup_at) : $b->event_date->copy()->startOfDay())
                    : ($b->delivery_at ? Carbon::parse($b->delivery_at) : $b->event_date->copy()->startOfDay());
                $availableRitten->push(['booking' => $b, 'type' => $role, 'role' => $role, 'datum' => $datum]);
            }
            if ($b->pickup_open && ! $b->pickup_staff_id) {
                $datum = $b->booking_type === 'to_go'
                    ? ($b->customer_return_at ? Carbon::parse($b->customer_return_at) : $b->event_date->copy()->startOfDay())
                    : ($b->pickup_at ? Carbon::parse($b->pickup_at) : $b->event_date->copy()->startOfDay());
                $availableRitten->push(['booking' => $b, 'type' => 'pickup', 'role' => 'pickup', 'datum' => $datum]);
            }
        }
        $availableRitten = $availableRitten->sortBy('datum')->values();

        // Antwoord van deze medewerker per rit: "booking_id|role" => 'yes' | 'no'
        $myResponses = RideSignup::withoutGlobalScopes()
            ->where('staff_id', $staff->id)
            ->whereIn('booking_id', $openBookings->pluck('id'))
            ->get()
            ->mapWithKeys(fn ($s) => [$s->booking_id . '|' . $s->role => $s->response]);

        return view('staff.portal', compact(
            'staff', 'tab',
            'deliveryBookings', 'pickupBookings',
            'pastDeliveryBookings', 'pastPickupBookings',
            'existingHours', 'allStaffRitten',
            'availableRitten', 'myResponses'
        ));
    }

    /** Medewerker meldt zich aan voor een open rit ("ik kan deze") */
    public function signup(Request $request, string $token): RedirectResponse
    {
        return $this->recordResponse($request, $token, 'yes');
    }

    /** Medewerker geeft aan een open rit NIET te kunnen ("ik kan niet") */
    public function decline(Request $request, string $token): RedirectResponse
    {
        return $this->recordResponse($request, $token, 'no');
    }

    /** Gedeelde afhandeling voor aanmelden (yes) en afwijzen (no) */
    private function recordResponse(Request $request, string $token, string $response): RedirectResponse
    {
        $staff = $this->resolveStaff($token);

        $validated = $request->validate([
            'booking_id' => ['required', 'integer'],
            'role'       => ['required', 'in:delivery,pickup,handover'],
        ]);

        // De rit moet openstaan, ongeclaimd zijn en bij hetzelfde account horen
        $openCol   = $validated['role'] === 'pickup' ? 'pickup_open' : 'delivery_open';
        $staffCol  = $validated['role'] === 'pickup' ? 'pickup_staff_id' : 'delivery_staff_id';

        $booking = Booking::withoutGlobalScopes()
            ->where('id', $validated['booking_id'])
            ->where('account_id', $staff->account_id)
            ->where('status', 'confirmed')
            ->where($openCol, true)
            ->whereNull($staffCol)
            ->first();

        if (! $booking) {
            return redirect(route('staff.portal', $token) . '?tab=beschikbaar')
                ->with('hours_error', 'Deze rit is niet meer beschikbaar.');
        }

        // updateOrCreate: één antwoord per medewerker per rit; van mening veranderen mag
        RideSignup::withoutGlobalScopes()->updateOrCreate(
            [
                'booking_id' => $booking->id,
                'role'       => $validated['role'],
                'staff_id'   => $staff->id,
            ],
            [
                'account_id' => $booking->account_id,
                'response'   => $response,
            ]
        );

        // Beheerder alleen mailen bij een positieve aanmelding, niet bij "kan niet"
        if ($response === 'yes') {
            $this->notifyAdminSignup($booking, $staff, $validated['role']);
            $msg = 'Aangemeld! Je beheerder bevestigt wie de rit doet.';
        } else {
            $msg = 'Genoteerd dat je deze rit niet kunt — je wordt er niet meer voor gevraagd.';
        }

        return redirect(route('staff.portal', $token) . '?tab=beschikbaar')
            ->with('hours_success', $msg);
    }

    /** Medewerker maakt zijn antwoord (aangemeld of afgewezen) ongedaan */
    public function withdraw(Request $request, string $token): RedirectResponse
    {
        $staff = $this->resolveStaff($token);

        $validated = $request->validate([
            'booking_id' => ['required', 'integer'],
            'role'       => ['required', 'in:delivery,pickup,handover'],
        ]);

        RideSignup::withoutGlobalScopes()
            ->where('staff_id', $staff->id)
            ->where('booking_id', $validated['booking_id'])
            ->where('role', $validated['role'])
            ->delete();

        return redirect(route('staff.portal', $token) . '?tab=beschikbaar')
            ->with('hours_success', 'Antwoord ongedaan gemaakt.');
    }

    /** Mail de beheerder dat een medewerker zich heeft aangemeld voor een rit */
    private function notifyAdminSignup(Booking $booking, Staff $staff, string $role): void
    {
        try {
            $account = $booking->account;
            if (! $account?->email) return;

            $rolLabel = match($role) {
                'delivery' => 'Bezorging',
                'pickup'   => 'Ophaling',
                'handover' => 'Afgifte (To Go)',
                default    => $role,
            };
            $planningUrl = route('bookings.staff-planning');
            $datum = $booking->event_date?->translatedFormat('l j F Y');

            $htmlBody = '
            <div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;">
                <h2 style="margin:0 0 8px;font-size:18px;">🙋 Aanmelding voor een rit</h2>
                <p style="color:#64748b;margin:0 0 20px;"><strong>'.$staff->name.'</strong> heeft zich aangemeld voor de <strong>'.$rolLabel.'</strong> van boeking <strong>'.$booking->booking_number.'</strong> ('.$booking->customer_name.')'.($datum ? ' op '.$datum : '').'.</p>
                <a href="'.$planningUrl.'" style="display:inline-block;background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">Toewijzen in planning →</a>
            </div>';

            app(MailService::class)->sendRaw(
                $account->email,
                '🙋 Aanmelding — ' . $staff->name . ' (' . $booking->booking_number . ')',
                $htmlBody
            );
        } catch (\Exception $e) {
            \Log::error('notifyAdminSignup mail fout: ' . $e->getMessage());
        }
    }

    public function submitHours(Request $request, string $token): RedirectResponse
    {
        $staff = $this->resolveStaff($token);

        $validated = $request->validate([
            'booking_id' => ['required', 'integer'],
            'role'       => ['required', 'in:delivery,pickup,handover'],
            'hours'      => ['required', 'numeric', 'min:0.25', 'max:24'],
            'km'         => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ]);

        // Veiligheid: controleer dat deze boeking echt aan deze medewerker toebehoort
        $booking = \App\Models\Booking::withoutGlobalScopes()
            ->where('id', $validated['booking_id'])
            ->where(function ($q) use ($staff) {
                $q->where('delivery_staff_id', $staff->id)
                  ->orWhere('pickup_staff_id', $staff->id);
            })
            ->firstOrFail();

        // updateOrCreate voorkomt duplicaten
        $entry = StaffHours::withoutGlobalScopes()->updateOrCreate(
            [
                'staff_id'   => $staff->id,
                'booking_id' => $booking->id,
                'role'       => $validated['role'],
            ],
            [
                'account_id'   => $booking->account_id,
                'hours'        => $validated['hours'],
                'km'           => isset($validated['km']) && $validated['km'] > 0 ? $validated['km'] : null,
                'staff_note'   => null,
                'status'       => 'pending',
                'hours_approved' => null,
                'approved_at'  => null,
                'submitted_at' => now(),
            ]
        );

        // Stuur notificatiemail naar beheerder
        try {
            $account = $booking->account;
            if ($account?->email) {
                $approveUrl = route('staff-hours.show', $entry->id);
                $rolLabel = match($validated['role']) {
                    'delivery' => 'Bezorging',
                    'pickup'   => 'Ophaling',
                    'handover' => 'Afgifte (To Go)',
                    default    => $validated['role'],
                };
                $htmlBody = '
                <div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;">
                    <h2 style="margin:0 0 8px;font-size:18px;">⏱ Uren ingediend</h2>
                    <p style="color:#64748b;margin:0 0 20px;">'.$staff->name.' heeft uren ingediend voor boeking <strong>'.$booking->booking_number.'</strong> ('.$booking->customer_name.').</p>
                    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                        <tr style="background:#f8fafc;">
                            <td style="padding:8px 12px;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">Rol</td>
                            <td style="padding:8px 12px;font-size:13px;border-bottom:1px solid #e2e8f0;">'.$rolLabel.'</td>
                        </tr>
                        <tr>
                            <td style="padding:8px 12px;font-size:13px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">Uren</td>
                            <td style="padding:8px 12px;font-size:13px;border-bottom:1px solid #e2e8f0;">'.number_format($validated['hours'], 2, ',', '').' uur</td>
                        </tr>
                        '.($entry->km > 0 ? '<tr style="background:#f8fafc;"><td style="padding:8px 12px;font-size:13px;font-weight:600;color:#64748b;">Privé km</td><td style="padding:8px 12px;font-size:13px;">'.number_format($entry->km, 0, ',', '').' km — €'.number_format($entry->km_allowance, 2, ',', '').' vergoeding</td></tr>' : '').'
                    </table>
                    <a href="'.$approveUrl.'" style="display:inline-block;background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">Uren goedkeuren →</a>
                </div>';

                app(MailService::class)->sendRaw(
                    $account->email,
                    '⏱ Uren ingediend — ' . $staff->name . ' (' . $booking->booking_number . ')',
                    $htmlBody
                );
            }
        } catch (\Exception $e) {
            \Log::error('StaffPortal uren mail fout: ' . $e->getMessage());
        }

        // Redirect terug naar het juiste tabblad
        $isPast = $booking->event_date->isPast();
        $redirectUrl = route('staff.portal', $token) . ($isPast ? '?tab=archief' : '');

        return redirect($redirectUrl)
            ->with('hours_success', 'Uren succesvol ingediend! Je beheerder ontvangt een melding.');
    }

    /** Markeer een booking-rit (booking_id + role) als gecombineerd met een andere rit. */
    public function markCombined(Request $request, string $token): RedirectResponse
    {
        $staff = $this->resolveStaff($token);

        $validated = $request->validate([
            'booking_id'    => ['required', 'integer'],
            'role'          => ['required', 'in:delivery,pickup,handover'],
            'combined_with' => ['required', 'string', 'regex:/^\d+:(delivery|pickup|handover)$/'],
        ], [
            'combined_with.regex' => 'Kies een geldige rit uit de lijst.',
        ]);

        [$combinedBookingId, $combinedRole] = explode(':', $validated['combined_with']);
        $combinedBookingId = (int) $combinedBookingId;

        // Kan niet met zichzelf gecombineerd worden
        if ($combinedBookingId === (int) $validated['booking_id'] && $combinedRole === $validated['role']) {
            return back()->with('hours_error', 'Kies een andere rit dan deze zelf.');
        }

        // Verifieer dat de huidige rit van deze medewerker is
        $booking = \App\Models\Booking::withoutGlobalScopes()
            ->where('id', $validated['booking_id'])
            ->where(function ($q) use ($staff) {
                $q->where('delivery_staff_id', $staff->id)
                  ->orWhere('pickup_staff_id', $staff->id);
            })
            ->firstOrFail();

        // Verifieer dat de doelrit ook van deze medewerker is, met de juiste rol-staff koppeling
        $combinedWith = \App\Models\Booking::withoutGlobalScopes()
            ->where('id', $combinedBookingId)
            ->where(function ($q) use ($staff, $combinedRole) {
                if ($combinedRole === 'pickup') {
                    $q->where('pickup_staff_id', $staff->id);
                } else {
                    // delivery + handover (To Go) gaan beide via delivery_staff_id
                    $q->where('delivery_staff_id', $staff->id);
                }
            })
            ->firstOrFail();

        // Mag niet markeren als de uren al goedgekeurd of uitbetaald zijn
        $existing = StaffHours::withoutGlobalScopes()
            ->where('staff_id', $staff->id)
            ->where('booking_id', $booking->id)
            ->where('role', $validated['role'])
            ->first();

        if ($existing && in_array($existing->status, ['approved', 'paid'])) {
            return back()->with('hours_error', 'Deze uren zijn al goedgekeurd of uitbetaald en kunnen niet meer als combinatierit gemarkeerd worden.');
        }

        StaffHours::withoutGlobalScopes()->updateOrCreate(
            [
                'staff_id'   => $staff->id,
                'booking_id' => $booking->id,
                'role'       => $validated['role'],
            ],
            [
                'account_id'               => $booking->account_id,
                'combined_with_booking_id' => $combinedWith->id,
                'combined_with_role'       => $combinedRole,
                'hours'                    => 0,
                'km'                       => null,
                'hours_approved'           => null,
                'approved_at'              => null,
                'status'                   => 'combined',
                'submitted_at'             => now(),
            ]
        );

        $roleLabel = match($combinedRole) {
            'delivery' => 'bezorging',
            'pickup'   => 'ophaling',
            'handover' => 'afgifte',
            default    => 'rit',
        };

        $isPast = $booking->event_date->isPast();
        $redirectUrl = route('staff.portal', $token) . ($isPast ? '?tab=archief' : '');

        return redirect($redirectUrl)
            ->with('hours_success', '🔗 Gemarkeerd als combinatie met de ' . $roleLabel . ' van boeking ' . $combinedWith->booking_number . '.');
    }

    /** Medewerker draait combinatie-markering terug — booking gaat weer naar "nog niet ingediend". */
    public function unmarkCombined(Request $request, string $token, int $hoursId): RedirectResponse
    {
        $staff = $this->resolveStaff($token);

        $staffHours = StaffHours::withoutGlobalScopes()->findOrFail($hoursId);

        if ($staffHours->staff_id !== $staff->id) abort(403);
        if ($staffHours->status !== 'combined') {
            return back()->with('hours_error', 'Alleen combinatieritten kunnen teruggedraaid worden.');
        }

        $booking = $staffHours->booking;
        $staffHours->delete();

        $isPast = $booking && $booking->event_date->isPast();
        $redirectUrl = route('staff.portal', $token) . ($isPast ? '?tab=archief' : '');

        return redirect($redirectUrl)
            ->with('hours_success', 'Combinatie-markering teruggedraaid. Je kunt nu alsnog je uren indienen.');
    }
}
