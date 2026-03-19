<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MailTemplateController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OfferPublicController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\TravelCostController;
use Illuminate\Support\Facades\Route;

// Publieke klantportaal (geen auth)
Route::get('/boeking/{token}', [PortalController::class, 'show'])->name('portal.show');
Route::post('/boeking/{token}/betalen', [PortalController::class, 'startPayment'])->name('portal.payment-start');
Route::get('/boeking/{token}/betaling-return', [PortalController::class, 'paymentReturn'])->name('portal.payment-return');
Route::post('/boeking/{token}/betaling-webhook', [PortalController::class, 'paymentWebhook'])->name('portal.payment-webhook');
Route::get('/boeking/{token}/ontwerp', [PortalController::class, 'stripDesignView'])->name('portal.strip-design');
Route::post('/boeking/{token}/ontwerp-feedback', [PortalController::class, 'submitStripFeedback'])->name('portal.strip-feedback');
Route::post('/boeking/{token}/strip-review', [PortalController::class, 'stripReview'])->name('portal.strip-review');
Route::post('/boeking/{token}/strip-input', [PortalController::class, 'submitStripInput'])->name('portal.strip-input');
Route::get('/boeking/{token}/factuur', [PortalController::class, 'factuurDownload'])->name('portal.factuur-download');

// Intake flow (klant vult in na bevestiging)
Route::get('/boeking/{token}/intake', [IntakeController::class, 'show'])->name('portal.intake');
Route::post('/boeking/{token}/intake/{step}', [IntakeController::class, 'saveStep'])->name('portal.intake.save');

// Publieke offertepagina (geen auth)
Route::get('/offerte/{token}', [OfferPublicController::class, 'show'])->name('offer.show');
Route::post('/offerte/{token}/accept', [OfferPublicController::class, 'accept'])->name('offer.accept');

// Inloggen
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Beveiligde routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Reiskosten berekening (AJAX)
    Route::get('/reiskosten', [TravelCostController::class, 'calculate'])->name('reiskosten.calculate');
    Route::get('/api/analytics/trends', [DashboardController::class, 'trends'])->name('analytics.trends');

    // Leads
    Route::resource('leads', LeadController::class);
    Route::post('leads/{lead}/notitie', [LeadController::class, 'addNote'])->name('leads.add-note');
    Route::post('leads/{lead}/afwijzen', [LeadController::class, 'afwijzen'])->name('leads.afwijzen');

    // Boekingen
    // Routes MUST come before resource route to avoid {booking} catching them
    Route::get('bookings/calendar', [BookingController::class, 'calendar'])->name('bookings.calendar');
    Route::get('bookings/follow-up', [BookingController::class, 'followUp'])->name('bookings.follow-up');
    Route::get('bookings/planning', [BookingController::class, 'planning'])->name('bookings.planning');
    Route::get('bookings/unit-availability', [BookingController::class, 'unitAvailability'])->name('bookings.unit-availability');
    Route::post('bookings/{booking}/gallery', [BookingController::class, 'saveGallery'])->name('bookings.save-gallery');
    Route::post('bookings/{booking}/create-invoice', [BookingController::class, 'createInvoice'])->name('bookings.create-invoice');
    Route::post('bookings/{booking}/strip-design', [BookingController::class, 'uploadStripDesign'])->name('bookings.strip-design');
    Route::delete('bookings/{booking}/strip-design', [BookingController::class, 'deleteStripDesign'])->name('bookings.strip-design-delete');
    Route::post('bookings/{booking}/strip-status', [BookingController::class, 'updateStripStatus'])->name('bookings.strip-status');
    Route::resource('bookings', BookingController::class);

    // Offertes
    Route::resource('offers', OfferController::class)->except(['index']);
    Route::post('offers/{offer}/send', [OfferController::class, 'send'])->name('offers.send');

    // Assets (voorraad)
    Route::resource('assets', AssetController::class);

    // Mailtemplates
    Route::get('mail-templates', [MailTemplateController::class, 'index'])->name('mail-templates.index');
    Route::get('mail-templates/{mailTemplate}/edit', [MailTemplateController::class, 'edit'])->name('mail-templates.edit');
    Route::put('mail-templates/{mailTemplate}', [MailTemplateController::class, 'update'])->name('mail-templates.update');
    Route::post('mail-templates/{mailTemplate}/test', [MailTemplateController::class, 'sendTest'])->name('mail-templates.test');

    // Admin
    Route::get('admin/gebruikers', [AdminController::class, 'users'])->name('admin.users');
    Route::get('admin/gebruikers/nieuw', [AdminController::class, 'createUser'])->name('admin.users.create');
    Route::post('admin/gebruikers', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::get('admin/gebruikers/{user}/bewerken', [AdminController::class, 'editUser'])->name('admin.users.edit');
    Route::put('admin/gebruikers/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::get('admin/instellingen', [AdminController::class, 'settings'])->name('admin.settings');
    Route::put('admin/instellingen/{account}', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
});
