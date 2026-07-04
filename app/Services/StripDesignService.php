<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gedeelde logica voor het toevoegen van een fotostrip-ontwerp aan een boeking —
 * gebruikt door zowel de handmatige upload (BookingController) als de AI-ontwerp-
 * generator (DesignGeneratorController), zodat beide dezelfde mockup/mail-flow delen.
 */
class StripDesignService
{
    /**
     * Stel desgewenst een mockup samen van een strip-afbeelding.
     * Geeft het (mogelijk nieuwe) opslagpad terug; bij een PDF of een fout blijft het origineel.
     */
    public function applyMockupIfImage(string $path, ?string $mime): string
    {
        if (! str_starts_with((string) $mime, 'image/')) {
            return $path;
        }

        try {
            $blob     = app(StripMockupService::class)->render(Storage::disk('public')->path($path));
            $mockPath = 'strip-designs/mockup_' . Str::random(20) . '.jpg';
            Storage::disk('public')->put($mockPath, $blob);

            return $mockPath;
        } catch (\Throwable $e) {
            Log::error('Strip-mockup samenstellen mislukt: ' . $e->getMessage());

            return $path;
        }
    }

    /**
     * Voeg een nieuw ontwerp toe aan de boeking (strip_designs-lijst + strip_design_url),
     * zet strip_status op 'review', en mail de klant zodra het ontwerp daadwerkelijk wijzigt.
     */
    public function attachDesign(Booking $booking, string $url, string $filename): void
    {
        $oudDesignUrl = $booking->strip_design_url;

        $designs   = $booking->strip_designs ?? [];
        $designs[] = [
            'url'         => $url,
            'filename'    => $filename,
            'uploaded_at' => now()->toIso8601String(),
        ];

        $updateData = [
            'strip_design_url' => $url,
            'strip_status'     => 'review',
            'strip_designs'    => $designs,
        ];

        if ($url !== $oudDesignUrl) {
            $updateData['strip_notes']       = null;
            $updateData['strip_feedback']    = null;
            $updateData['strip_feedback_at'] = null;
        }

        $booking->update($updateData);

        if ($url !== $oudDesignUrl && $booking->customer_email) {
            $booking->load('account');
            app(MailService::class)->send('customer_strip_design_added', $booking, $booking->customer_email);
        }
    }
}
