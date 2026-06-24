<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyBookings extends Command
{
    protected $signature = 'bookings:import-legacy
                            {file : Pad naar het CSV-bestand}
                            {--account-id=1 : Account ID (standaard 1 = Utrecht)}
                            {--dry-run : Simuleer de import zonder iets op te slaan}';

    protected $description = 'Importeer boekingen uit het oude systeem (geen mails)';

    // Statussen oud systeem → nieuw systeem
    private array $statusMap = [
        'unconfirmed'               => 'confirmed',
        'confirmed'                 => 'confirmed',
        'customer details confirmed' => 'confirmed',
        'full amount paid'          => 'confirmed',
    ];

    public function handle(): int
    {
        $file      = $this->argument('file');
        $accountId = (int) $this->option('account-id');
        $dryRun    = $this->option('dry-run');

        if (! file_exists($file)) {
            $this->error("Bestand niet gevonden: $file");
            return self::FAILURE;
        }

        $rows = $this->parseCsv($file);
        $this->info(sprintf('CSV geladen: %d rijen', count($rows)));

        if ($dryRun) {
            $this->warn('⚠️  DRY-RUN — er wordt niets opgeslagen');
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $doImport = function () use ($rows, $accountId, $dryRun, &$imported, &$skipped, &$errors) {
            foreach ($rows as $i => $row) {
                $line = $i + 2; // +1 header, +1 zero-index

                try {
                    $data = $this->mapRow($row, $accountId);
                } catch (\Exception $e) {
                    $errors[] = "Rij $line: " . $e->getMessage();
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [DRY] %s | %s | %s | %s | €%s | %s',
                        $data['event_date'],
                        $data['customer_name'],
                        $data['customer_email'] ?? '—',
                        $data['event_location'] ?? '—',
                        number_format($data['total_price'], 2, ',', '.'),
                        $data['payment_status']
                    ));
                    $imported++;
                    continue;
                }

                // Echte import — sla op via Eloquent (triggert booking_number + public_token generatie)
                $booking = new Booking($data);
                $booking->account_id = $accountId; // expliciet zetten
                $booking->save();

                $imported++;
            }
        };

        if ($dryRun) {
            $doImport();
        } else {
            DB::transaction($doImport);
        }

        $this->newLine();
        $this->info("✅ Geïmporteerd: $imported");
        if ($skipped > 0) {
            $this->warn("⚠️  Overgeslagen: $skipped");
        }
        foreach ($errors as $err) {
            $this->error($err);
        }

        return self::SUCCESS;
    }

    private function mapRow(array $row, int $accountId): array
    {
        $statusRaw     = strtolower(trim($row['Status'] ?? ''));
        $status        = $this->statusMap[$statusRaw] ?? 'confirmed';
        $remaining     = (float) ($row['Remaining Balance'] ?? 0);
        $total         = (float) ($row['Total'] ?? 0);
        $subtotal      = (float) ($row['Subtotal'] ?? 0);

        // Betaalstatus afleiden uit restbedrag
        if ($statusRaw === 'full amount paid' || ($total > 0 && $remaining <= 0)) {
            $paymentStatus = 'paid';
        } elseif ($remaining > 0 && $remaining < $total) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'unpaid';
        }

        // Naam samenvoegen
        $firstName = trim($row['First Name'] ?? '');
        $lastName  = trim($row['Last Name']  ?? '');
        $name      = trim("$firstName $lastName") ?: 'Onbekend';

        // Telefoonnummer: Mobile heeft voorkeur, max 50 tekens
        $phone = trim($row['Mobile'] ?? '') ?: trim($row['Phone'] ?? '') ?: null;
        if ($phone) {
            $phone = substr($phone, 0, 50);
        }

        // Locatie
        $venueName    = trim($row['Venue Name']    ?? '');
        $venueAddress = trim($row['Venue Address'] ?? '');
        $venuePost    = trim($row['Venue Postcode'] ?? '');
        $location     = $venueName ?: ($venueAddress ?: null);

        // Adresonderdelen (voor event_address / event_postcode)
        // Venue Address bevat vaak "nr, straat, wijk, stad, provincie"
        $eventAddress  = $venueAddress ?: null;
        $eventPostcode = $venuePost ?: null;

        // Notities opbouwen
        $noteParts = [];
        $oldId     = trim($row['Booking ID'] ?? '');
        if ($oldId)                        $noteParts[] = "Oud ID: $oldId";
        if (!empty($row['Event Name']))    $noteParts[] = "Event: " . trim($row['Event Name']);
        $package = trim(str_replace('[CUSTOM] ', '', $row['Package(s)'] ?? ''));
        if ($package && $package !== 'NULL') $noteParts[] = "Pakket: $package";
        $extras = trim(str_replace('[CUSTOM] ', '', $row['Extras'] ?? ''));
        if ($extras && $extras !== 'NULL') $noteParts[] = "Extra's: $extras";
        if (!empty($row['Staff']))         $noteParts[] = "Staff: " . trim($row['Staff']);
        $notes = $noteParts ? '[Import] ' . implode(' | ', $noteParts) : null;

        // Klantentype
        $company      = trim($row['Company'] ?? '');
        $customerType = $company ? 'zakelijk' : 'particulier';

        // Datum aangemaakt
        $submittedDate = $this->parseDate($row['Submitted Date'] ?? null) ?? now();
        $eventDate     = $this->parseDate($row['Event Date'] ?? null);

        if (! $eventDate) {
            throw new \Exception("Ongeldige event_date: '{$row['Event Date']}'");
        }

        return [
            'account_id'          => $accountId,
            'booking_type'        => 'full_service',
            'customer_name'       => $name,
            'customer_email'      => trim($row['Email'] ?? '') ?: null,
            'customer_phone'      => $phone,
            'customer_type'       => $customerType,
            'company_name'        => $company ?: null,
            'event_date'          => $eventDate->toDateString(),
            'event_location'      => $location,
            'event_address'       => $eventAddress,
            'event_postcode'      => $eventPostcode,
            'event_notes'         => $notes,
            'base_price'          => $subtotal,
            'total_price'         => $total,
            'amount_total'        => $total,
            'status'              => $status,
            'payment_status'      => $paymentStatus,
            'review_mail_sent_at' => now(), // voorkomt automatische review-mail
            'created_at'          => $submittedDate,
            'updated_at'          => $submittedDate,
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value || $value === 'NULL') return null;
        try {
            return Carbon::parse(trim($value));
        } catch (\Exception) {
            return null;
        }
    }

    private function parseCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        $header = null;
        $rows   = [];

        while (($line = fgetcsv($handle, 0, ',', '"')) !== false) {
            if ($header === null) {
                $header = $line;
                continue;
            }
            if (count($line) === count($header)) {
                $rows[] = array_combine($header, $line);
            }
        }

        fclose($handle);
        return $rows;
    }
}
