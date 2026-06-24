<?php

namespace App\Http\Controllers;

use App\Models\StaffHours;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class StaffHoursController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'all');

        $query = StaffHours::with(['staff', 'booking', 'combinedWithBooking'])
            ->orderBy('submitted_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        } elseif (! $request->has('include_combined')) {
            // Standaard 'Alles' verbergt combinaties (ruisreductie)
            $query->where('status', '!=', 'combined');
        }

        $entries = $query->get();

        $counts = [
            'pending'  => StaffHours::where('status', 'pending')->count(),
            'approved' => StaffHours::where('status', 'approved')->count(),
            'paid'     => StaffHours::where('status', 'paid')->count(),
            'combined' => StaffHours::where('status', 'combined')->count(),
        ];

        return view('staff-hours.index', compact('entries', 'status', 'counts'));
    }

    public function show(StaffHours $staffHours): View
    {
        $staffHours->load(['staff', 'booking']);
        return view('staff-hours.approve', compact('staffHours'));
    }

    public function approve(Request $request, StaffHours $staffHours): RedirectResponse
    {
        $validated = $request->validate([
            'hours_approved' => ['required', 'numeric', 'min:0', 'max:24'],
            'admin_note'     => ['nullable', 'string', 'max:1000'],
        ]);

        $staffHours->update([
            'hours_approved' => $validated['hours_approved'],
            'admin_note'     => $validated['admin_note'] ?? null,
            'status'         => 'approved',
            'approved_at'    => now(),
        ]);

        return redirect()->route('staff-hours.index')
            ->with('success', 'Uren goedgekeurd voor ' . $staffHours->staff->name . '.');
    }

    public function markPaid(StaffHours $staffHours): RedirectResponse
    {
        $staffHours->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return redirect()->route('staff-hours.index')
            ->with('success', $staffHours->staff->name . ' gemarkeerd als uitbetaald.');
    }

    public function destroy(StaffHours $staffHours): RedirectResponse
    {
        $staffHours->delete();
        return redirect()->route('staff-hours.index')
            ->with('success', 'Urenverzoek verwijderd.');
    }

    public function revert(StaffHours $staffHours): RedirectResponse
    {
        if ($staffHours->status === 'paid') {
            $staffHours->update([
                'status'  => 'approved',
                'paid_at' => null,
            ]);
            $msg = 'Uitbetaling teruggedraaid — status is nu goedgekeurd.';
        } elseif ($staffHours->status === 'approved') {
            $staffHours->update([
                'status'       => 'pending',
                'hours_approved' => null,
                'approved_at'  => null,
            ]);
            $msg = 'Goedkeuring teruggedraaid — status is nu wacht op goedkeuring.';
        } else {
            $msg = 'Niets om terug te draaien.';
        }

        return redirect()->route('staff-hours.show', $staffHours)
            ->with('success', $msg);
    }
}
