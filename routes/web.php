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
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskListController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffPortalController;
use App\Http\Controllers\StaffHoursController;
use App\Http\Controllers\CanvaTemplateController;
use App\Http\Controllers\BriefingController;
use Illuminate\Support\Facades\Route;

// iCal feed (geen auth, token-beveiligd)
Route::get('/ical/{token}.ics', [BookingController::class, 'ical'])->name('bookings.ical');

// Medewerker planning (geen login, token-beveiligd)
Route::get('/medewerker/{token}', [StaffPortalController::class, 'show'])->name('staff.portal');
Route::post('/medewerker/{token}/uren', [StaffPortalController::class, 'submitHours'])->name('staff.portal.hours.submit');
Route::post('/medewerker/{token}/uren/combineer', [StaffPortalController::class, 'markCombined'])->name('staff.portal.hours.combine');
Route::post('/medewerker/{token}/uren/{hoursId}/combineer-terug', [StaffPortalController::class, 'unmarkCombined'])->name('staff.portal.hours.uncombine');
Route::post('/medewerker/{token}/aanmelden', [StaffPortalController::class, 'signup'])->name('staff.portal.signup');
Route::post('/medewerker/{token}/kan-niet', [StaffPortalController::class, 'decline'])->name('staff.portal.decline');
Route::post('/medewerker/{token}/afmelden', [StaffPortalController::class, 'withdraw'])->name('staff.portal.withdraw');

// Publiek open-rittenbord (read-only, voor delen in WhatsApp-groep)
Route::get('/ritten/{account}', [\App\Http\Controllers\RidesBoardController::class, 'show'])->name('rides.board');

// Webhook voor inkomende leads (bijv. Elementor) — geen auth, geen CSRF, token-beveiligd
Route::post('/webhook/lead/{token}', [WebhookController::class, 'lead'])->name('webhook.lead');

// Publieke klantportaal (geen auth) — max 60 requests/minuut per IP
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/boeking/{token}', [PortalController::class, 'show'])->name('portal.show');
    Route::post('/boeking/{token}/betalen', [PortalController::class, 'startPayment'])->name('portal.payment-start');
    Route::get('/boeking/{token}/betaling-return', [PortalController::class, 'paymentReturn'])->name('portal.payment-return');
    Route::get('/boeking/{token}/ontwerp', [PortalController::class, 'stripDesignView'])->name('portal.strip-design');
    Route::post('/boeking/{token}/ontwerp-feedback', [PortalController::class, 'submitStripFeedback'])->name('portal.strip-feedback');
    Route::post('/boeking/{token}/strip-review', [PortalController::class, 'stripReview'])->name('portal.strip-review');
    Route::post('/boeking/{token}/strip-input', [PortalController::class, 'submitStripInput'])->name('portal.strip-input');
    Route::post('/boeking/{token}/strip-method', [PortalController::class, 'setStripMethod'])->name('portal.strip-method');
    Route::post('/boeking/{token}/strip-self-tool', [PortalController::class, 'setStripSelfTool'])->name('portal.strip-self-tool');
    Route::post('/boeking/{token}/strip-template', [PortalController::class, 'selectStripTemplate'])->name('portal.strip-template-select');
    Route::post('/boeking/{token}/strip-reset', [PortalController::class, 'resetStripChoice'])->name('portal.strip-reset');
    Route::post('/boeking/{token}/return-time', [PortalController::class, 'requestReturnTime'])->name('portal.return-time');
    Route::get('/boeking/{token}/factuur', [PortalController::class, 'factuurDownload'])->name('portal.factuur-download');

    // Intake flow (klant vult in na bevestiging)
    Route::get('/boeking/{token}/intake', [IntakeController::class, 'show'])->name('portal.intake');
    Route::post('/boeking/{token}/intake/{step}', [IntakeController::class, 'saveStep'])->name('portal.intake.save');
    Route::post('/boeking/{token}/approve-pickup', [IntakeController::class, 'approvePickup'])->name('portal.intake.approve-pickup');

    // Publieke offertepagina
    Route::get('/offerte/{token}', [OfferPublicController::class, 'show'])->name('offer.show');
    Route::post('/offerte/{token}/accept', [OfferPublicController::class, 'accept'])->name('offer.accept');

    // Publieke galerij (branded wrapper met iframe)
    Route::get('/g/{token}', [\App\Http\Controllers\GalleryController::class, 'show'])->name('gallery.show');
});

// Betaling webhook — geen CSRF, geen throttle (Mollie server-to-server)
Route::post('/boeking/{token}/betaling-webhook', [PortalController::class, 'paymentWebhook'])->name('portal.payment-webhook');

// Inloggen — max 5 pogingen per minuut per IP
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Beveiligde routes
Route::middleware('auth')->group(function () {
    Route::get('/', fn() => redirect()->route('bookings.index'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Reiskosten berekening (AJAX)
    Route::get('/reiskosten', [TravelCostController::class, 'calculate'])->name('reiskosten.calculate');
    Route::get('/api/analytics/trends', [DashboardController::class, 'trends'])->name('analytics.trends');

    // Leads
    Route::get('bellijst', [LeadController::class, 'callList'])->name('leads.call-list');
    Route::resource('leads', LeadController::class);
    Route::post('leads/{lead}/notitie', [LeadController::class, 'addNote'])->name('leads.add-note');
    Route::post('leads/{lead}/afwijzen', [LeadController::class, 'afwijzen'])->name('leads.afwijzen');
    Route::post('leads/{lead}/akkoord', [LeadController::class, 'akkoord'])->name('leads.akkoord');
    Route::patch('leads/{lead}/follow-up', [LeadController::class, 'updateFollowUp'])->name('leads.follow-up');
    Route::patch('leads/{lead}/kans', [LeadController::class, 'updateChance'])->name('leads.kans');

    // Boekingen
    // Routes MUST come before resource route to avoid {booking} catching them
    Route::get('bookings/calendar', [BookingController::class, 'calendar'])->name('bookings.calendar');
    Route::get('bookings/follow-up', [BookingController::class, 'followUp'])->name('bookings.follow-up');
    Route::get('bookings/planning', [BookingController::class, 'planning'])->name('bookings.planning');
    Route::get('bookings/staff-planning', [BookingController::class, 'staffPlanning'])->name('bookings.staff-planning');
    Route::post('bookings/staff-planning/open-all', [BookingController::class, 'openAllUnassigned'])->name('bookings.open-all-rides');
    Route::post('bookings/{booking}/assign-staff', [BookingController::class, 'assignStaff'])->name('bookings.assign-staff');
    Route::post('bookings/{booking}/toggle-ride-open', [BookingController::class, 'toggleRideOpen'])->name('bookings.toggle-ride-open');
    Route::get('bookings/archive', [BookingController::class, 'archive'])->name('bookings.archive');
    Route::get('bookings/unit-availability', [BookingController::class, 'unitAvailability'])->name('bookings.unit-availability');
    Route::get('bookings/day-logistics', [BookingController::class, 'dayLogistics'])->name('bookings.day-logistics');
    Route::get('bookings/{booking}/design-modal', [BookingController::class, 'designModal'])->name('bookings.design-modal');
    Route::post('bookings/{booking}/gallery', [BookingController::class, 'saveGallery'])->name('bookings.save-gallery');
    Route::post('bookings/{booking}/gallery-overslaan', [BookingController::class, 'skipGallery'])->name('bookings.skip-gallery');
    Route::post('bookings/{booking}/create-invoice', [BookingController::class, 'createInvoice'])->name('bookings.create-invoice');
    Route::post('bookings/{booking}/sync-invoice', [BookingController::class, 'syncInvoiceStatus'])->name('bookings.sync-invoice');
    Route::post('bookings/sync-all-payments', [BookingController::class, 'syncAllPayments'])->name('bookings.sync-all-payments');
    Route::post('bookings/{booking}/manual-invoice', [BookingController::class, 'saveManualInvoiceNumber'])->name('bookings.manual-invoice');
    Route::post('bookings/{booking}/extra-invoice', [BookingController::class, 'addExtraInvoice'])->name('bookings.extra-invoice.add');
    Route::delete('bookings/{booking}/extra-invoice/{index}', [BookingController::class, 'removeExtraInvoice'])->name('bookings.extra-invoice.remove');
    Route::post('bookings/{booking}/skip-invoice', [BookingController::class, 'toggleSkipInvoice'])->name('bookings.skip-invoice');
    Route::post('bookings/{booking}/payment-status', [BookingController::class, 'updatePaymentStatus'])->name('bookings.payment-status');
    Route::post('bookings/{booking}/resend-confirmation', [BookingController::class, 'resendConfirmation'])->name('bookings.resend-confirmation');
    Route::post('bookings/{booking}/resend-gallery-mail', [BookingController::class, 'resendGalleryMail'])->name('bookings.resend-gallery-mail');
    Route::post('bookings/{booking}/return-time-request', [BookingController::class, 'handleReturnTimeRequest'])->name('bookings.return-time-request');
    Route::post('bookings/{booking}/strip-design', [BookingController::class, 'uploadStripDesign'])->name('bookings.strip-design');
    Route::delete('bookings/{booking}/strip-design', [BookingController::class, 'deleteStripDesign'])->name('bookings.strip-design-delete');
    Route::delete('bookings/{booking}/delivery-image', [BookingController::class, 'deleteDeliveryImage'])->name('bookings.delivery-image-delete');
    Route::post('bookings/{booking}/strip-status', [BookingController::class, 'updateStripStatus'])->name('bookings.strip-status');
    Route::resource('bookings', BookingController::class);

    // Offertes
    Route::resource('offers', OfferController::class)->except(['index']);
    Route::post('offers/{offer}/send', [OfferController::class, 'send'])->name('offers.send');

    // Assets (voorraad)
    Route::resource('assets', AssetController::class);
    Route::resource('staff', StaffController::class)->except(['show']);

    // Canva templates galerij (admin beheert)
    Route::resource('canva-templates', CanvaTemplateController::class)
         ->parameters(['canva-templates' => 'canvaTemplate'])
         ->except(['show']);

    // AI Ontwerp-generator (fotostrips genereren)
    Route::get('ontwerp-generator', [\App\Http\Controllers\DesignGeneratorController::class, 'index'])->name('design.index');
    Route::post('ontwerp-generator', [\App\Http\Controllers\DesignGeneratorController::class, 'generate'])->name('design.generate');
    Route::post('ontwerp-generator/prompt', [\App\Http\Controllers\DesignGeneratorController::class, 'updatePrompt'])->name('design.prompt.update');

    // Briefings (PDF voor medewerker)
    Route::resource('briefings', BriefingController::class)->except(['show']);
    Route::get('briefings/{briefing}/pdf', [BriefingController::class, 'pdf'])->name('briefings.pdf');

    // Medewerker uren
    Route::get('staff-uren', [StaffHoursController::class, 'index'])->name('staff-hours.index');
    Route::get('staff-uren/{staffHours}/goedkeuren', [StaffHoursController::class, 'show'])->name('staff-hours.show');
    Route::post('staff-uren/{staffHours}/goedkeuren', [StaffHoursController::class, 'approve'])->name('staff-hours.approve');
    Route::post('staff-uren/{staffHours}/betaald', [StaffHoursController::class, 'markPaid'])->name('staff-hours.mark-paid');
    Route::post('staff-uren/{staffHours}/terugdraaien', [StaffHoursController::class, 'revert'])->name('staff-hours.revert');
    Route::delete('staff-uren/{staffHours}', [StaffHoursController::class, 'destroy'])->name('staff-hours.destroy');

    // Taken (task manager)
    Route::get('taken',                         [TaskController::class, 'index'])->name('tasks.index');
    Route::post('taken/reorder',                [TaskController::class, 'reorder'])->name('tasks.reorder');
    Route::post('taken',                        [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('taken/{task}',                [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('taken/{task}',               [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('taken/{task}/toggle',          [TaskController::class, 'toggle'])->name('tasks.toggle');
    Route::post('taken/{task}/flag',            [TaskController::class, 'flag'])->name('tasks.flag');
    Route::post('takenlijsten',                 [TaskListController::class, 'store'])->name('task-lists.store');
    Route::patch('takenlijsten/{taskList}',     [TaskListController::class, 'update'])->name('task-lists.update');
    Route::delete('takenlijsten/{taskList}',    [TaskListController::class, 'destroy'])->name('task-lists.destroy');

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
