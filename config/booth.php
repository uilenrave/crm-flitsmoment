<?php

/**
 * Instellingen voor de eigen photobooth-software.
 *
 * Een nieuwe functie uitrollen: voeg hier een regel toe met 'default' => false, bouw de functie
 * in de booth-app achter deze sleutel, en zet hem daarna per booth aan in de CRM (Beheer → Booths).
 */
return [

    // Updatekanalen: 'beta' voor de testbooth, 'stable' voor de rest.
    'channels' => [
        'stable' => 'Stable',
        'beta'   => 'Beta (testbooth)',
    ],

    // Geen app-update als deze booth binnen zoveel uur een bevestigde boeking heeft.
    'update_freeze_hours' => 24,

    // Functieschakelaars: sleutel => label + standaardwaarde.
    'features' => [
        'gif'        => ['label' => 'GIF / boomerang', 'default' => false],
        'qr_sharing' => ['label' => 'Delen via QR',    'default' => false],
        'ai_strips'  => ['label' => 'AI-strips',       'default' => false],
    ],

    // Gebeurtenissen die een booth mag melden, met label. 'mail' => true stuurt direct een mail.
    'events' => [
        'update_ok'       => ['label' => 'Update geslaagd',  'mail' => true,  'ok' => true],
        'update_failed'   => ['label' => 'Update mislukt',   'mail' => true,  'ok' => false],
        'rollback'        => ['label' => 'Rollback',         'mail' => true,  'ok' => false],
        'selftest_failed' => ['label' => 'Zelftest gefaald', 'mail' => true,  'ok' => false],
        'info'            => ['label' => 'Melding',          'mail' => false, 'ok' => true],
    ],

    // Zelfde zelftest-storing niet vaker mailen dan eens per zoveel minuten per booth.
    'alert_cooldown_minutes' => 60,
];
