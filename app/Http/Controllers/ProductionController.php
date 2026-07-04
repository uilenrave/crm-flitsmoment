<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Booking;
use App\Scopes\AccountScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // Publiek (geen login) — vervangt de Google Drive-map voor de photobooths
    // ──────────────────────────────────────────────────────────────

    /** Lijst van aankomende events met een download-knop zodra er een productie-PNG klaarstaat. */
    public function board(string $token): View
    {
        $account = Account::where('production_token', $token)->firstOrFail();

        $bookings = Booking::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $account->id)
            ->where('status', 'confirmed')
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->get(['id', 'booking_number', 'customer_name', 'event_date', 'production_file_path', 'production_file_at']);

        return view('production.board', compact('account', 'bookings', 'token'));
    }

    /** Download de klaarstaande productie-PNG voor één boeking. */
    public function download(string $token, Booking $booking): StreamedResponse
    {
        $account = Account::where('production_token', $token)->firstOrFail();

        abort_if($booking->account_id !== $account->id, 404);
        abort_if(! $booking->production_file_path || ! Storage::disk('public')->exists($booking->production_file_path), 404, 'Geen productiebestand beschikbaar.');

        return Storage::disk('public')->download(
            $booking->production_file_path,
            "fotostrip-{$booking->booking_number}.png"
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Admin
    // ──────────────────────────────────────────────────────────────

    /** Beheer: lijst aankomende boekingen, PNG-uploaden per boeking, kopieerbare publieke link. */
    public function index(): View
    {
        $bookings = Booking::where('status', 'confirmed')
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->get();

        $account = auth()->user()->account;

        return view('production.index', compact('bookings', 'account'));
    }

    /** Upload/vervang de productie-PNG van een boeking. Zet strip_status altijd op 'ready'. */
    public function upload(Request $request, Booking $booking): RedirectResponse
    {
        $request->validate([
            'production_file' => ['required', 'file', 'mimes:png', 'max:20480'],
        ]);

        if ($booking->production_file_path) {
            Storage::disk('public')->delete($booking->production_file_path);
        }

        $filename = 'production/' . $booking->booking_number . '_' . Str::random(8) . '.png';
        Storage::disk('public')->put($filename, file_get_contents($request->file('production_file')->getRealPath()));

        $booking->update([
            'production_file_path' => $filename,
            'production_file_at'   => now(),
            'strip_status'         => 'ready',
        ]);

        return back()->with('success', "PNG toegevoegd voor {$booking->booking_number} — boeking staat op \"Ontwerp klaar\".");
    }

    /** Verwijder het productiebestand van een boeking (bijv. om opnieuw te uploaden). */
    public function destroy(Booking $booking): RedirectResponse
    {
        if ($booking->production_file_path) {
            Storage::disk('public')->delete($booking->production_file_path);
        }

        $booking->update(['production_file_path' => null, 'production_file_at' => null]);

        return back()->with('success', 'Productiebestand verwijderd.');
    }
}
