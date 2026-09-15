<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domein van de takenapp
    |--------------------------------------------------------------------------
    |
    | Staat TODO_DOMAIN in .env, dan draait de takenapp op dat domein
    | (bijv. todo.flitsmoment.nl) in de root. Staat het er niet, dan hangt
    | de app onder /todo op het CRM-domein — handig voor lokaal testen.
    |
    */
    'domain' => env('TODO_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Standaarddrempels voor de CRM-signalen
    |--------------------------------------------------------------------------
    |
    | Per account overschrijfbaar via het instellingenscherm; die waarden
    | staan in accounts.todo_settings en winnen van deze defaults.
    |
    */
    'signals' => [
        // Offerte verstuurd, X dagen stil → "nabellen"
        'offer_followup_days' => 5,

        // X dagen na de klus nog geen gallery_url → "galerij uploaden"
        'gallery_days' => 3,

        // Klus binnen X dagen met een open bezorging/ophaling → "medewerker zoeken"
        'shift_days' => 10,
    ],

];
