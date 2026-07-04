<?php

/*
|--------------------------------------------------------------------------
| Fotostrip mockup
|--------------------------------------------------------------------------
| Instellingen voor het samenstellen van een strip-mockup: achtergrond+logo
| eronder, de strip met schaduw, en een plakbandje transparant erover.
| Posities zijn fracties (0-1) van het achtergrond-canvas, zodat ze
| onafhankelijk van de exacte afmetingen werken. Pas aan om te finetunen.
*/

return [
    // Assets in resources/mockups/
    'background_file' => 'background.jpg',
    'tape_file'       => 'tape.png',

    // Plaatsing van de strip op de achtergrond
    // Achtergrond is nu 606x706 (compacter dan de oude 606x1026) — herijkt zodat de strip
    // weinig rand boven/onder overhoudt i.p.v. het ruime formaat van de oude achtergrond.
    'strip' => [
        'center_x'   => 0.50, // horizontaal midden (fractie van breedte)
        'top'        => 0.06, // bovenkant strip (fractie van hoogte)
        'width'      => 0.34, // breedte strip (fractie van breedte)
        'max_height' => 0.90, // max hoogte strip (fractie van hoogte)
    ],

    // Slagschaduw onder de strip
    'shadow' => [
        'opacity'  => 45, // 0-100
        'sigma'    => 16, // zachtheid van de blur
        'offset_x' => 0,
        'offset_y' => 12,
    ],

    // Plakbandje (bovenin, over de strip)
    'tape' => [
        'center_x' => 0.50, // horizontaal midden
        'center_y' => 0.06, // verticaal midden van het bandje (fractie van hoogte) — gelijk aan strip.top
        'width'    => 0.25, // breedte (fractie van breedte)
    ],

    // Uitvoer
    'format'  => 'jpeg',
    'quality' => 90,
];
