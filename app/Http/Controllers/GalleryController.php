<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function show(string $token)
    {
        $booking = Booking::withoutGlobalScopes()
            ->where('gallery_token', $token)
            ->whereNotNull('gallery_url')
            ->firstOrFail();

        return view('gallery.show', compact('booking'));
    }
}
