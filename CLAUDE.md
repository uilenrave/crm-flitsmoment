# CRM Flitsmoment — instructies voor Claude

## Stack
- Laravel 12, MySQL, Blade, Tailwind CSS
- Hosting: Hostinger shared hosting, SSH port 65002
- PHP: `/opt/alt/php84/usr/bin/php`
- Remote pad: `/home/u493340040/domains/crm.flitsmoment.nl/laravel`

## Deployen
Gebruik altijd `./deploy.sh` — dit maakt een backup vóór elke deploy.
Nooit losse bestanden uploaden via SCP zonder eerst een backup te maken.

## Database migrations — 3 vaste regels

1. **Nooit een kolom direct droppen.** Eerst de kolom `nullable()` maken en de code aanpassen zodat die de kolom niet meer gebruikt. Na een paar weken pas een tweede migratie om hem te droppen.

2. **Nieuwe kolommen altijd `nullable()` of met `default()`.** Bestaande rijen krijgen anders een SQL error bij de migratie.

3. **Bij twijfel: eerst `./deploy.sh --pull` → lokaal testen met productiedata → dan pas deployen.**

## Architectuur

### Multi-tenant
- `AccountScope` global scope filtert alles op `account_id`
- Altijd checken of queries door deze scope lopen

### Photobooth units
- Photobooths werken unit-gebaseerd: één `BookingItem` per unit met `unit_number`
- Niet-photobooth assets werken quantity-gebaseerd (`unit_number = null`)
- Legacy boekingen (vóór het unit-systeem) hebben `unit_number = null` — in edit.blade.php worden deze automatisch toegewezen aan units 1..quantity

### Formulieren
- Photobooth unit-selectie werkt via `<span onclick="toggleUnit(this)">` + dynamische `<input type="hidden">` — GEEN checkboxes met `display:none` (onbetrouwbaar bij form-submission)

### Strip status flow
`waiting_input` → `designing` → `review` → `accepted` → `ready`

### Automatische status-overgangen
- Boeking wordt automatisch `completed` zodra `gallery_url` wordt ingevuld
