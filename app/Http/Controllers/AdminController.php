<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AiUsageEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    private function isHeadquarters(): bool
    {
        return auth()->user()->account->type === 'headquarters';
    }

    // ─── Gebruikersbeheer ────────────────────────────────────────

    public function users(): View
    {
        $this->authorizeAdmin();

        // Headquarters ziet alle gebruikers gegroepeerd per account
        if ($this->isHeadquarters()) {
            $users = User::with('account')->orderBy('account_id')->orderBy('first_name')->get();
        } else {
            $users = User::where('account_id', auth()->user()->account_id)->orderBy('first_name')->get();
        }

        return view('admin.users', compact('users'));
    }

    public function editUser(User $user): View
    {
        $this->authorizeAdmin();
        abort_unless($this->isHeadquarters() || $user->account_id === auth()->user()->account_id, 403);

        return view('admin.edit-user', compact('user'));
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin();
        abort_unless($this->isHeadquarters() || $user->account_id === auth()->user()->account_id, 403);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:75'],
            'last_name'  => ['required', 'string', 'max:75'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role'       => ['required', 'in:admin,manager,employee'],
            'status'     => ['required', 'in:active,inactive'],
            'password'   => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users')->with('success', 'Gebruiker bijgewerkt.');
    }

    public function createUser(): View
    {
        $this->authorizeAdmin();

        $accounts = $this->isHeadquarters()
            ? Account::orderBy('name')->get()
            : collect([auth()->user()->account]);

        return view('admin.create-user', compact('accounts'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
            'first_name' => ['required', 'string', 'max:75'],
            'last_name'  => ['required', 'string', 'max:75'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'       => ['required', 'in:admin,manager,employee'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Niet-headquarters mag alleen eigen account
        if (! $this->isHeadquarters()) {
            $data['account_id'] = auth()->user()->account_id;
        }

        $data['status'] = 'active';

        User::create($data);

        return redirect()->route('admin.users')->with('success', 'Gebruiker aangemaakt.');
    }

    // ─── Account instellingen ─────────────────────────────────────

    public function settings(): View
    {
        $this->authorizeAdmin();

        $accounts = $this->isHeadquarters()
            ? Account::orderBy('name')->get()
            : collect([auth()->user()->account]);

        $aiUsage = $this->aiUsageByAccount($accounts->pluck('id'));

        return view('admin.settings', compact('accounts', 'aiUsage'));
    }

    /**
     * AI-verbruik per account: aantal generaties per provider, totaal én deze maand.
     * Vorm: [account_id => ['gemini' => ['total'=>x,'month'=>y], 'openai' => [...]]].
     */
    private function aiUsageByAccount($accountIds): array
    {
        $monthStart = now()->startOfMonth();

        $usage = [];
        foreach ($accountIds as $id) {
            $usage[$id] = [
                'gemini' => ['total' => 0, 'month' => 0],
                'openai' => ['total' => 0, 'month' => 0],
            ];
        }

        $pivot = function ($rows, string $bucket) use (&$usage) {
            foreach ($rows as $row) {
                $provider = $row->provider === AiUsageEvent::PROVIDER_OPENAI ? 'openai' : 'gemini';
                if (isset($usage[$row->account_id])) {
                    $usage[$row->account_id][$provider][$bucket] = (int) $row->c;
                }
            }
        };

        $pivot(
            AiUsageEvent::whereIn('account_id', $accountIds)
                ->selectRaw('account_id, provider, count(*) as c')
                ->groupBy('account_id', 'provider')->get(),
            'total'
        );
        $pivot(
            AiUsageEvent::whereIn('account_id', $accountIds)
                ->where('created_at', '>=', $monthStart)
                ->selectRaw('account_id, provider, count(*) as c')
                ->groupBy('account_id', 'provider')->get(),
            'month'
        );

        return $usage;
    }

    public function updateSettings(Request $request, Account $account): RedirectResponse
    {
        $this->authorizeAdmin();
        abort_unless($this->isHeadquarters() || $account->id === auth()->user()->account_id, 403);

        $data = $request->validate([
            'mollie_key'           => ['nullable', 'string', 'max:255'],
            'mollie_enabled'       => ['nullable', 'boolean'],
            'eboekhouden_enabled'  => ['nullable', 'boolean'],
            'eboekhouden_api_key'  => ['nullable', 'string', 'max:255'],
            'origin_address'       => ['nullable', 'string', 'max:255'],
            'travel_free_km'       => ['nullable', 'integer', 'min:0'],
            'travel_cost_per_km'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['mollie_enabled']      = $request->boolean('mollie_enabled');
        $data['eboekhouden_enabled'] = $request->boolean('eboekhouden_enabled');

        // Lege keys = terugvallen op globale key
        if (empty($data['mollie_key'])) {
            $data['mollie_key'] = null;
        }
        if (empty($data['eboekhouden_api_key'])) {
            $data['eboekhouden_api_key'] = null;
        }

        $account->update($data);

        return redirect()->route('admin.settings')->with('success', 'Instellingen opgeslagen voor ' . $account->name . '.');
    }
}
