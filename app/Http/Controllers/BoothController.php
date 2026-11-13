<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\BoothEvent;
use App\Models\BoothRelease;
use App\Models\BoothToken;
use App\Services\BoothFleetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Beheer van de photobooth-machines: koppelen, updatekanaal, versie vastzetten,
 * functieschakelaars en status. BoothToken heeft geen AccountScope → altijd zelf op account filteren.
 */
class BoothController extends Controller
{
    private function accountId(): int
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        // De eigen booth-software draait alleen bij de hoofdvestiging (Utrecht), niet bij franchises.
        abort_unless(auth()->user()->account?->type === 'headquarters', 404);

        return (int) auth()->user()->account_id;
    }

    private function findBooth(int $id): BoothToken
    {
        return BoothToken::where('account_id', $this->accountId())->whereKey($id)->firstOrFail();
    }

    /** Keuzelijst "assetId:unit" => label voor alle photobooth-units van het account. */
    private function boothOptions(): array
    {
        $options = [];
        foreach (Asset::where('category', 'photobooth')->orderBy('name')->get() as $asset) {
            $units = max(1, (int) $asset->stock);
            for ($u = 1; $u <= $units; $u++) {
                $options[$asset->id . ':' . $u] = $units > 1
                    ? $asset->name . ' — ' . $asset->unitTag($u)
                    : ($asset->boothName($u) ?? $asset->name);
            }
        }

        return $options;
    }

    public function index(BoothFleetService $fleet): View
    {
        $accountId = $this->accountId();

        $booths = $fleet->booths($accountId);
        $latest = $fleet->latestVersions($booths);

        $rows = $booths->map(fn (BoothToken $b) => [
            'booth'  => $b,
            'behind' => $fleet->isBehind($b, $latest),
            'policy' => $b->updatePolicy(),
        ]);

        $events = BoothEvent::where('account_id', $accountId)
            ->with('booth.asset')
            ->latest()
            ->limit(25)
            ->get();

        return view('booths.index', [
            'rows'         => $rows,
            'latest'       => $latest,
            'events'       => $events,
            'boothOptions' => $this->boothOptions(),
            'releases'     => BoothRelease::all()->sort(fn ($x, $y) => version_compare($y->version, $x->version))->values(),
            'channels'     => config('booth.channels'),
            'features'     => config('booth.features'),
        ]);
    }

    /** Nieuwe booth koppelen: maakt een token aan en toont de platte waarde één keer. */
    public function store(Request $request): RedirectResponse
    {
        $accountId = $this->accountId();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'booth' => ['nullable', Rule::in(array_keys($this->boothOptions()))],
        ]);

        [$assetId, $unit] = $this->splitBooth($data['booth'] ?? null);

        $plain = Str::random(64);

        BoothToken::create([
            'account_id'  => $accountId,
            'asset_id'    => $assetId,
            'unit_number' => $unit,
            'name'        => $data['name'],
            'token'       => BoothToken::hashToken($plain),
        ]);

        return redirect()->route('booths.index')
            ->with('success', "Booth \"{$data['name']}\" gekoppeld.")
            ->with('new_token', $plain);
    }

    public function update(Request $request, int $booth): RedirectResponse
    {
        $model = $this->findBooth($booth);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'booth'          => ['nullable', Rule::in(array_keys($this->boothOptions()))],
            'update_channel' => ['required', Rule::in(array_keys(config('booth.channels')))],
            'pinned_version' => ['nullable', 'string', 'max:30', 'regex:/^\d+(\.\d+){1,3}([\-+][0-9A-Za-z.\-]+)?$/'],
            'features'       => ['nullable', 'array'],
        ]);

        [$assetId, $unit] = $this->splitBooth($data['booth'] ?? null);

        // Elke bekende functie expliciet opslaan (aan/uit), zodat een later gewijzigde default
        // een bewuste keuze per booth niet stilletjes omgooit.
        $features = [];
        foreach (array_keys(config('booth.features')) as $key) {
            $features[$key] = ! empty($data['features'][$key]);
        }

        $model->update([
            'name'           => $data['name'],
            'asset_id'       => $assetId,
            'unit_number'    => $unit,
            'update_channel' => $data['update_channel'],
            'pinned_version' => $data['pinned_version'] ?: null,
            'features'       => $features,
        ]);

        return redirect()->route('booths.index')->with('success', "Booth \"{$model->name}\" opgeslagen.");
    }

    /** Token intrekken: de machine kan direct niet meer bij de API. De rij blijft bewaard. */
    public function revoke(int $booth): RedirectResponse
    {
        $model = $this->findBooth($booth);
        $model->update(['revoked_at' => now()]);

        return redirect()->route('booths.index')->with('success', "Token van \"{$model->name}\" ingetrokken.");
    }

    /** Een versie naar een ander kanaal zetten (beta → stable om hem naar alle booths uit te rollen). */
    public function releaseChannel(Request $request, BoothRelease $release): RedirectResponse
    {
        $this->accountId();

        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys(config('booth.channels')))],
        ]);

        $release->update(['channel' => $data['channel']]);

        return redirect()->route('booths.index')
            ->with('success', "Versie {$release->version} staat nu op {$data['channel']}.");
    }

    /** @return array{0: ?int, 1: ?int} */
    private function splitBooth(?string $value): array
    {
        if (! $value || ! str_contains($value, ':')) {
            return [null, null];
        }
        [$assetId, $unit] = explode(':', $value, 2);

        return [(int) $assetId, (int) $unit];
    }
}
