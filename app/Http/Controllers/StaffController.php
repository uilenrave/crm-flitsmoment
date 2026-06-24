<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $staff = Staff::orderBy('is_active', 'desc')->orderBy('name')->get();
        return view('staff.index', compact('staff'));
    }

    public function create(): View
    {
        return view('staff.form', ['person' => new Staff()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('staff-photos', 'public');
        }

        Staff::create($data);

        return redirect()->route('staff.index')->with('success', '✅ Medewerker toegevoegd.');
    }

    public function edit(Staff $staff): View
    {
        return view('staff.form', ['person' => $staff]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->boolean('remove_photo') && $staff->photo_path) {
            Storage::disk('public')->delete($staff->photo_path);
            $data['photo_path'] = null;
        }

        if ($request->hasFile('photo')) {
            // Verwijder oude foto voor nieuwe wordt opgeslagen
            if ($staff->photo_path) {
                Storage::disk('public')->delete($staff->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('staff-photos', 'public');
        }

        $staff->update($data);

        return redirect()->route('staff.index')->with('success', '✅ Medewerker bijgewerkt.');
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        if ($staff->photo_path) {
            Storage::disk('public')->delete($staff->photo_path);
        }
        $staff->delete();
        return redirect()->route('staff.index')->with('success', '🗑 Medewerker verwijderd.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:150'],
            'notes'     => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'photo'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
    }
}
