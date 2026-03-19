<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Scopes\AccountScope;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class IntakeController extends Controller
{
    public function show(string $token): View|\Illuminate\Http\RedirectResponse
    {
        $booking = $this->findBooking($token);

        if ($booking->intake_completed) {
            return redirect()->route('portal.show', $token);
        }

        return view('portal.intake', compact('booking'));
    }

    public function saveStep(Request $request, string $token, int $step): JsonResponse
    {
        $booking = $this->findBooking($token);

        if ($step < 1 || $step > 3) {
            return response()->json(['error' => 'Ongeldig stapnummer'], 422);
        }

        $validated = $this->validateStep($request, $step);

        // Handle file uploads for step 1
        $uploadedFiles = [];
        if ($step === 1 && $request->hasFile('branding_files')) {
            foreach ($request->file('branding_files') as $file) {
                $path = $file->store('intake-files/' . $booking->id, 'public');
                $uploadedFiles[] = Storage::disk('public')->url($path);
            }
        }

        // Merge into intake_data JSON
        $intakeData = $booking->intake_data ?? [];
        $intakeData["step_{$step}"] = array_merge(
            $validated,
            $uploadedFiles ? ['uploaded_files' => $uploadedFiles] : [],
            ['answered_at' => now()->toISOString()]
        );

        $updateData = [
            'intake_data' => $intakeData,
            'intake_current_step' => max($booking->intake_current_step, $step),
        ];

        // Sync denormalized columns for queryable data
        if ($step === 3) {
            $updateData['pickup_preference'] = $validated['pickup_preference'] ?? null;
            $updateData['pickup_contact_person'] = $validated['pickup_contact_person'] ?? null;
            $updateData['pickup_contact_time'] = $validated['pickup_time'] ?? null;
        }

        // Check completion
        $isComplete = $step === 3;
        if ($isComplete) {
            $updateData['intake_completed'] = true;
            $updateData['intake_completed_at'] = now();
        }

        $booking->update($updateData);

        if ($isComplete) {
            $this->notifyIntakeCompleted($booking);
        }

        return response()->json([
            'success' => true,
            'next_step' => $isComplete ? null : $step + 1,
            'completed' => $isComplete,
        ]);
    }

    private function findBooking(string $token): Booking
    {
        return Booking::withoutGlobalScope(AccountScope::class)
            ->where('public_token', $token)
            ->with('account')
            ->firstOrFail();
    }

    private function validateStep(Request $request, int $step): array
    {
        return match ($step) {
            1 => $request->validate([
                'design_choice' => ['required', 'in:self_input,template'],
                'branding_files' => ['nullable', 'array', 'max:5'],
                'branding_files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:20480'],
            ]),
            2 => $request->validate([
                'delivery_time_preference' => ['nullable', 'string', 'max:500'],
                'delivery_notes' => ['nullable', 'string', 'max:1000'],
            ]),
            3 => $request->validate([
                'pickup_preference' => ['required', 'in:same_evening,next_morning'],
                'pickup_time' => ['nullable', 'string', 'max:10'],
                'pickup_contact_person' => ['nullable', 'string', 'max:150'],
            ]),
        };
    }

    private function notifyIntakeCompleted(Booking $booking): void
    {
        $booking->loadMissing('account');
        $mail = app(MailService::class);

        if ($booking->account->email) {
            $mail->send('admin_intake_completed', $booking, $booking->account->email);
        }
        if ($booking->customer_email) {
            $mail->send('customer_intake_confirmed', $booking, $booking->customer_email);
        }
    }
}
