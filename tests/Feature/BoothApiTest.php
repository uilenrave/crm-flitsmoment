<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BoothToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BoothApiTest extends TestCase
{
    /**
     * Hermetisch schema: alleen de tabellen die dit endpoint raakt. De volledige migratieketen
     * is MySQL-specifiek (o.a. ALTER … MODIFY … ENUM) en draait niet op de sqlite-testdatabase,
     * dus we bouwen hier zelf een minimale, geïsoleerde set tabellen per test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['booth_tokens', 'booking_items', 'bookings', 'accounts'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('booking_number', 50);
            $table->string('customer_name', 150);
            $table->date('event_date');
            $table->text('event_location')->nullable();
            $table->string('status')->default('confirmed');
            $table->string('strip_status')->nullable();
            $table->string('strip_design_url')->nullable();
            $table->text('strip_designs')->nullable();
            $table->string('strip_format', 10)->nullable();
            $table->unsignedBigInteger('strip_template_id')->nullable();
            $table->string('gallery_token', 32)->nullable();
            $table->string('production_file_path')->nullable();
            $table->timestamp('production_file_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('name_snapshot', 150);
            $table->decimal('price_snapshot', 10, 2)->default(0);
            $table->smallInteger('quantity')->default(1);
            $table->unsignedTinyInteger('unit_number')->nullable();
            $table->timestamps();
        });

        Schema::create('booth_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name');
            $table->unsignedTinyInteger('unit_number')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    private function account(string $name = 'Test account'): Account
    {
        return Account::create(['name' => $name]);
    }

    /** @return array{0: BoothToken, 1: string} */
    private function token(Account $account, bool $revoked = false, ?int $unit = null): array
    {
        $plain = Str::random(64);
        $token = BoothToken::create([
            'account_id'  => $account->id,
            'name'        => 'Booth',
            'unit_number' => $unit,
            'token'       => BoothToken::hashToken($plain),
            'revoked_at'  => $revoked ? now() : null,
        ]);

        return [$token, $plain];
    }

    private function booking(Account $account, array $attrs = []): Booking
    {
        // withoutEvents: sla de BookingObserver (externe geocode) over in tests
        return Booking::withoutEvents(function () use ($account, $attrs) {
            return Booking::create(array_merge([
                'account_id'     => $account->id,
                'booking_number' => 'B-' . Str::upper(Str::random(6)),
                'customer_name'  => 'Jan & Marie',
                'event_date'     => now()->addDays(5)->toDateString(),
                'status'         => 'confirmed',
            ], $attrs));
        });
    }

    private function item(Booking $booking, ?int $unit): BookingItem
    {
        return BookingItem::create([
            'booking_id'     => $booking->id,
            'name_snapshot'  => 'Photobooth',
            'price_snapshot' => 0,
            'quantity'       => 1,
            'unit_number'    => $unit,
        ]);
    }

    private function callApi(string $plain, array $query = [])
    {
        $url = '/api/booth/bookings' . ($query ? '?' . http_build_query($query) : '');
        return $this->getJson($url, ['Authorization' => 'Bearer ' . $plain]);
    }

    public function test_zonder_token_geeft_401(): void
    {
        $this->getJson('/api/booth/bookings')->assertStatus(401);
    }

    public function test_ingetrokken_token_geeft_401(): void
    {
        $account = $this->account();
        [, $plain] = $this->token($account, revoked: true);

        $this->callApi($plain)->assertStatus(401);
    }

    public function test_token_van_account_a_geeft_nooit_boekingen_van_account_b(): void
    {
        $a = $this->account('A');
        $b = $this->account('B');

        $mine    = $this->booking($a, ['customer_name' => 'Van A']);
        $foreign = $this->booking($b, ['customer_name' => 'Van B']);

        [, $plain] = $this->token($a);

        $res = $this->callApi($plain)->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
        $res->assertJsonMissing(['customer_name' => 'Van B']);
    }

    public function test_geannuleerde_en_afgeronde_boekingen_zitten_niet_in_de_response(): void
    {
        $account = $this->account();
        $confirmed = $this->booking($account, ['status' => 'confirmed']);
        $cancelled = $this->booking($account, ['status' => 'cancelled']);
        $completed = $this->booking($account, ['status' => 'completed']);

        [, $plain] = $this->token($account);
        $ids = collect($this->callApi($plain)->assertOk()->json('data'))->pluck('id')->all();

        $this->assertEquals([$confirmed->id], $ids);
        $this->assertNotContains($cancelled->id, $ids);
        $this->assertNotContains($completed->id, $ids);
    }

    public function test_from_en_days_filteren_en_days_boven_90_wordt_afgekapt(): void
    {
        $account = $this->account();
        [, $plain] = $this->token($account);

        $inWindow  = $this->booking($account, ['event_date' => now()->addDays(3)->toDateString()]);
        $outWindow = $this->booking($account, ['event_date' => now()->addDays(20)->toDateString()]);
        $farFuture = $this->booking($account, ['event_date' => now()->addDays(95)->toDateString()]);

        // Standaard 14 dagen
        $ids = collect($this->callApi($plain)->json('data'))->pluck('id')->all();
        $this->assertContains($inWindow->id, $ids);
        $this->assertNotContains($outWindow->id, $ids);

        // days=200 wordt afgekapt op 90 → +95 valt buiten
        $ids2 = collect($this->callApi($plain, ['days' => 200])->json('data'))->pluck('id')->all();
        $this->assertContains($outWindow->id, $ids2);
        $this->assertNotContains($farFuture->id, $ids2);

        // from verschuift het venster
        $ids3 = collect($this->callApi($plain, ['from' => now()->addDays(15)->toDateString(), 'days' => 10])->json('data'))->pluck('id')->all();
        $this->assertContains($outWindow->id, $ids3);
        $this->assertNotContains($inWindow->id, $ids3);
    }

    public function test_unit_filtert_op_booking_item_unit_number(): void
    {
        $account = $this->account();
        [, $plain] = $this->token($account);

        $withUnit3 = $this->booking($account);
        $this->item($withUnit3, 3);
        $withUnit1 = $this->booking($account);
        $this->item($withUnit1, 1);

        $ids = collect($this->callApi($plain, ['unit' => 3])->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEquals([$withUnit3->id], $ids);
    }

    public function test_design_url_is_absoluut(): void
    {
        $account = $this->account();
        [, $plain] = $this->token($account);

        $booking = $this->booking($account, [
            'strip_status'     => 'ready',
            'strip_design_url' => '/storage/strip-designs/xyz.png',
            'strip_designs'    => [['url' => '/storage/strip-designs/xyz.png', 'filename' => 'mijn-strip.png']],
        ]);

        $res = $this->callApi($plain)->assertOk();
        $row = collect($res->json('data'))->firstWhere('id', $booking->id);

        $this->assertMatchesRegularExpression('#^https?://#', $row['strip']['design_url']);
        $this->assertStringEndsWith('/storage/strip-designs/xyz.png', $row['strip']['design_url']);
        $this->assertSame('mijn-strip.png', $row['strip']['design_filename']);
    }

    public function test_boeking_zonder_strip_komt_terug_met_null_status(): void
    {
        $account = $this->account();
        [, $plain] = $this->token($account);

        $booking = $this->booking($account, ['strip_status' => null, 'strip_design_url' => null]);

        $res = $this->callApi($plain)->assertOk();
        $row = collect($res->json('data'))->firstWhere('id', $booking->id);

        $this->assertNull($row['strip']['status']);
        $this->assertNull($row['strip']['design_url']);
        $this->assertNull($row['strip']['design_filename']);
    }

    public function test_productiebestand_alleen_voor_eigen_account(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('production/strip.png', 'PNGDATA');

        $a = $this->account('A');
        $b = $this->account('B');
        [, $plainA] = $this->token($a);
        [, $plainB] = $this->token($b);

        $booking = $this->booking($a, ['production_file_path' => 'production/strip.png', 'production_file_at' => now()]);
        $without = $this->booking($a);

        $row = collect($this->callApi($plainA)->assertOk()->json('data'))->firstWhere('id', $booking->id);
        $this->assertTrue($row['production']['available']);
        $this->assertStringEndsWith("/api/booth/bookings/{$booking->id}/production-file", $row['production']['url']);

        $url = "/api/booth/bookings/{$booking->id}/production-file";
        $res = $this->get($url, ['Authorization' => 'Bearer ' . $plainA])->assertOk();
        $this->assertSame('PNGDATA', $res->streamedContent());

        $this->get($url, ['Authorization' => 'Bearer ' . $plainB])->assertNotFound();
        $this->get("/api/booth/bookings/{$without->id}/production-file", ['Authorization' => 'Bearer ' . $plainA])->assertNotFound();
        $this->get($url)->assertStatus(401);
    }
}
