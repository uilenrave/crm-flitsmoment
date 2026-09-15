<?php

return [
    // Landelijke ZE-zone-bron (dezelfde data die opwegnaarzes.nl toont) — bereikbaar, WGS84.
    'source_url' => 'https://www.omleidingennet.nl/api/map/cm3d4xub800010cic73ie371f',

    // Alleen deze gemeenten inladen (leeg = alle actieve ZE-zones). Uit te breiden wanneer nodig.
    'relevant_cities' => ['Utrecht', 'Den Haag', 'Amsterdam', 'Amersfoort', 'Gouda', 'Leiden', 'Rotterdam'],
];
