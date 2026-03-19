<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Services\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MailTemplateController extends Controller
{
    public function index(): View
    {
        $templates  = MailTemplate::orderBy('recipient_type')->orderBy('name')->get();
        $recentLogs = MailLog::with('booking')->latest()->limit(30)->get();

        return view('mail-templates.index', compact('templates', 'recentLogs'));
    }

    public function edit(MailTemplate $mailTemplate): View
    {
        $logs = MailLog::with('booking')
            ->where('template_key', $mailTemplate->key)
            ->latest()
            ->limit(10)
            ->get();

        return view('mail-templates.edit', ['template' => $mailTemplate, 'logs' => $logs]);
    }

    public function update(Request $request, MailTemplate $mailTemplate): RedirectResponse
    {
        $data = $request->validate([
            'subject'   => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', false);

        $mailTemplate->update($data);

        return redirect()
            ->route('mail-templates.edit', $mailTemplate)
            ->with('success', "Template opgeslagen.");
    }

    /**
     * Stuur een testmail naar het ingelogde account.
     * Gebruikt de meest recente boeking als testdata.
     */
    public function sendTest(MailTemplate $mailTemplate): RedirectResponse
    {
        $booking = Booking::latest()->first();

        if (! $booking) {
            return back()->with('test_error', 'Geen boekingen gevonden om als testdata te gebruiken.');
        }

        $toEmail = auth()->user()->email ?? $booking->account->email;

        if (! $toEmail) {
            return back()->with('test_error', 'Geen e-mailadres gevonden om de testmail naartoe te sturen.');
        }

        $ok = app(MailService::class)->send($mailTemplate->key, $booking, $toEmail);

        if ($ok) {
            return back()->with('test_success', "Testmail verstuurd naar {$toEmail} (met data van boeking {$booking->booking_number}).");
        }

        return back()->with('test_error', 'Testmail mislukt. Controleer de mailconfiguratie en logbestand.');
    }
}
