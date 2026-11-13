<?php

namespace Tests\Feature;

use App\Mail\BoothDailyDigest;
use App\Mail\BoothEventNotification;
use App\Models\Account;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BoothEvent;
use App\Models\BoothToken;
use App\Services\BoothFleetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Config, heartbeat, events, update-vriesperiode en dagoverzicht van de booth-API. */
class BoothDeviceApiTest extends TestCase
{
    /** Hermetisch schema (zie BoothApiTest): de volledige migratieketen draait niet op sqlite. */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['booth_events', 'booth_tokens', 'booking_items', 'bookings', 'assets', 'accounts'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name');
            $table->string('category')->default('photobooth');
            $table->integer('stock')->default(1);
            $table->text('unit_names')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('booking_number', 50);
            $table->string('customer_name', 150);
            $table->date('event_date');
            $table->date('event_end_date')->nullable();
            $table->time('event_start_time')->nullable();
            $table->string('status')->default('confirmed');
            $table->timestamps();
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('name_snapshot', 150);
            $table->decimal('price_snapshot', 10, 2)->default(0);
            $table->smallInteger('quantity')->default(1);
            $table->unsignedTinyInteger('unit_number')->nullable();
            $table->timestamps();
        });

        Schema::create('booth_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('name');
            $table->unsignedTinyInteger('unit_number')->nullable();
            $table->string('update_channel', 20)->default('stable');
            $table->string('pinned_version', 30)->nullable();
            $table->text('features')->nullable();
            $table->string('app_version', 30)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->boolean('selftest_ok')->nullable();
            $table->text('last_status')->nullable();
            $table->unsignedInteger('prints_total')->nullable();
            $table->unsignedInteger('prints_total_at_digest')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booth_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('booth_token_id');
            $table->string('type', 40);
            $table->string('app_version', 30)->nullable();
            $table->text('message')->nullable();
            $table->text('data')->nullable();
            $table->timestamp('mailed_at')->nullable();
            $table->timestamps();
        });

        Mail::fake();
    }

    private function account(string $name = 'Test'): Account
    {
        return Account::forceCreate(['name' => $name, 'email' => Str::slug($name) . '@example.test', 'timezone' => 'Europe/Amsterdam']);
    }

    /** @return array{0: BoothToken, 1: string} */
    private function booth(Account $account, array $attrs = []): array
    {
        $plain = Str::random(64);
        $booth = BoothToken::create(array_merge([
            'account_id'  => $account->id,
            'name'        => 'Booth 1',
            'unit_number' => 1,
            'token'       => BoothToken::hashToken($plain),
        ], $attrs));

        return [$booth, $plain];
    }

    private function booking(Account $account, int $unit, string $date, string $status = 'confirmed'): Booking
    {
        $booking = Booking::withoutEvents(fn () => Booking::create([
            'account_id'     => $account->id,
            'booking_number' => 'B-' . Str::upper(Str::random(6)),
            'customer_name'  => 'Jan & Marie',
            'event_date'     => $date,
            'status'         => $status,
        ]));

        BookingItem::create([
            'booking_id'     => $booking->id,
            'name_snapshot'  => 'Photobooth',
            'price_snapshot' => 0,
            'quantity'       => 1,
            'unit_number'    => $unit,
        ]);

        return $booking;
    }

    private function auth(string $plain): array
    {
        return ['Authorization' => 'Bearer ' . $plain];
    }

    public function test_config_zonder_token_geeft_401(): void
    {
        $this->getJson('/api/booth/config')->assertStatus(401);
        $this->postJson('/api/booth/heartbeat', ['app_version' => '1.0.0'])->assertStatus(401);
        $this->postJson('/api/booth/events', ['type' => 'update_ok'])->assertStatus(401);
    }

    public function test_config_geeft_kanaal_functies_en_update_toegestaan(): void
    {
        $account = $this->account();
        [, $plain] = $this->booth($account, ['update_channel' => 'beta', 'features' => ['gif' => true, 'bestaat_niet' => true]]);

        $res = $this->getJson('/api/booth/config', $this->auth($plain))->assertOk();

        $res->assertJsonPath('data.update.channel', 'beta')
            ->assertJsonPath('data.update.allowed', true)
            ->assertJsonPath('data.features.gif', true)
            ->assertJsonPath('data.features.ai_strips', false);

        $this->assertArrayNotHasKey('bestaat_niet', $res->json('data.features'));
    }

    public function test_update_geblokkeerd_bij_boeking_binnen_24_uur_op_deze_unit(): void
    {
        $account = $this->account();
        [, $plain] = $this->booth($account, ['unit_number' => 2]);

        $this->booking($account, 2, now('Europe/Amsterdam')->toDateString());

        $this->getJson('/api/booth/config', $this->auth($plain))
            ->assertOk()
            ->assertJsonPath('data.update.allowed', false);
    }

    public function test_update_toegestaan_bij_boeking_op_andere_unit_ver_weg_of_geannuleerd(): void
    {
        $account = $this->account();
        $other   = $this->account('Ander');
        [, $plain] = $this->booth($account, ['unit_number' => 2]);

        $today = now('Europe/Amsterdam')->toDateString();
        $this->booking($account, 3, $today);                                              // andere unit
        $this->booking($account, 2, now('Europe/Amsterdam')->addDays(5)->toDateString()); // ver weg
        $this->booking($account, 2, $today, 'cancelled');                                 // geannuleerd
        $this->booking($other, 2, $today);                                                // ander account

        $res = $this->getJson('/api/booth/config', $this->auth($plain))->assertOk();

        $res->assertJsonPath('data.update.allowed', true);
        $this->assertNotNull($res->json('data.update.next_booking_date'));
    }

    public function test_heartbeat_slaat_status_op_en_geeft_config_terug(): void
    {
        $account = $this->account();
        [$booth, $plain] = $this->booth($account);

        $this->postJson('/api/booth/heartbeat', [
            'app_version'     => '1.2.0',
            'selftest'        => ['camera' => true, 'printer' => true],
            'prints_total'    => 120,
            'media_remaining' => 310,
        ], $this->auth($plain))->assertOk()->assertJsonPath('data.update.channel', 'stable');

        $booth->refresh();
        $this->assertSame('1.2.0', $booth->app_version);
        $this->assertTrue($booth->selftest_ok);
        $this->assertSame(120, $booth->prints_total);
        $this->assertSame(310, $booth->last_status['media_remaining']);
        $this->assertNotNull($booth->last_heartbeat_at);

        Mail::assertNothingSent();
    }

    public function test_gefaalde_zelftest_mailt_een_keer_en_niet_bij_elke_heartbeat(): void
    {
        $account = $this->account();
        [$booth, $plain] = $this->booth($account);

        $payload = ['app_version' => '1.2.0', 'selftest' => ['camera' => true, 'printer' => false]];

        $this->postJson('/api/booth/heartbeat', $payload, $this->auth($plain))->assertOk();
        $this->postJson('/api/booth/heartbeat', $payload, $this->auth($plain))->assertOk();

        $this->assertFalse($booth->refresh()->selftest_ok);
        $this->assertSame(1, BoothEvent::where('type', 'selftest_failed')->count());
        Mail::assertSent(BoothEventNotification::class, 1);
        Mail::assertSent(BoothEventNotification::class, fn ($m) => $m->hasTo($account->email));
    }

    public function test_event_wordt_vastgelegd_en_gemaild_en_onbekend_type_geweigerd(): void
    {
        $account = $this->account();
        [$booth, $plain] = $this->booth($account);

        $this->postJson('/api/booth/events', [
            'type' => 'update_ok', 'app_version' => '1.3.0', 'message' => 'Van 1.2.0 naar 1.3.0',
        ], $this->auth($plain))->assertCreated();

        $this->assertSame('1.3.0', $booth->refresh()->app_version);
        $this->assertSame(1, BoothEvent::where('account_id', $account->id)->where('type', 'update_ok')->count());
        Mail::assertSent(BoothEventNotification::class, 1);

        $this->postJson('/api/booth/events', ['type' => 'iets_anders'], $this->auth($plain))->assertStatus(422);
    }

    public function test_achterlopende_booth_wordt_herkend_per_kanaal(): void
    {
        $account = $this->account();
        [$old]    = $this->booth($account, ['name' => 'Oud', 'app_version' => '1.2.0']);
        [$new]    = $this->booth($account, ['name' => 'Nieuw', 'unit_number' => 2, 'app_version' => '1.10.0']);
        [$beta]   = $this->booth($account, ['name' => 'Beta', 'unit_number' => 3, 'app_version' => '2.0.0', 'update_channel' => 'beta']);
        [$pinned] = $this->booth($account, ['name' => 'Vast', 'unit_number' => 4, 'app_version' => '1.1.0', 'pinned_version' => '1.1.0']);

        $fleet  = app(BoothFleetService::class);
        $latest = $fleet->latestVersions($fleet->booths($account->id));

        $this->assertSame(['stable' => '1.10.0', 'beta' => '2.0.0'], $latest);
        $this->assertTrue($fleet->isBehind($old, $latest));
        $this->assertFalse($fleet->isBehind($new, $latest));
        $this->assertFalse($fleet->isBehind($beta, $latest));
        $this->assertFalse($fleet->isBehind($pinned, $latest));
    }

    public function test_dagoverzicht_mailt_per_account_en_toont_prints_sinds_vorige_keer(): void
    {
        $account = $this->account('Mijn');
        $other   = $this->account('Ander');
        [$booth] = $this->booth($account, ['app_version' => '1.2.0', 'prints_total' => 150, 'prints_total_at_digest' => 100]);
        $this->booth($other, ['name' => 'Van ander']);
        $this->booking($account, 1, now('Europe/Amsterdam')->addDay()->toDateString());

        $fleet = app(BoothFleetService::class);
        $data  = $fleet->digestData($account);

        $this->assertCount(1, $data['rows']);
        $this->assertSame(50, $data['rows'][0]['prints_since']);
        $this->assertCount(1, $data['upcoming']);

        $this->artisan('booth:daily-digest')->assertSuccessful();

        Mail::assertSent(BoothDailyDigest::class, 2);
        Mail::assertSent(BoothDailyDigest::class, fn ($m) => $m->hasTo($account->email) && count($m->data['rows']) === 1);
        $this->assertSame(150, $booth->refresh()->prints_total_at_digest);

        // De mail-views renderen zonder fouten.
        $html = (new BoothDailyDigest($account, $fleet->digestData($account)))->render();
        $this->assertStringContainsString('Booth 1', $html);
    }
}
