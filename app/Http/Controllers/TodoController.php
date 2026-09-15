<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Task;
use App\Models\TaskList;
use App\Services\TodoQuickParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * De takenapp op todo.flitsmoment.nl — de "rustige lijst".
 *
 * Draait op dezelfde codebase en dezelfde tabellen als /taken in het CRM,
 * maar met een eigen, kale weergave en eigen menu-items (Kopen, Leren).
 */
class TodoController extends Controller
{
    public function __construct(private TodoQuickParser $parser) {}

    // ── Weergaven ────────────────────────────────────────────────────────────

    public function vandaag(): View
    {
        $vandaag = now()->toDateString();

        $taken = $this->base()
            ->ofType(Task::TYPE_TAAK)
            ->where(fn ($q) => $q->whereDate('due_date', '<=', $vandaag)->orWhere('flagged', true))
            ->get();

        $kopen = $this->base()
            ->ofType(Task::TYPE_KOPEN)
            ->whereDate('due_date', '<=', $vandaag)
            ->get();

        $leren = $this->base()
            ->ofType(Task::TYPE_LEREN)
            ->whereDate('due_date', '<=', $vandaag)
            ->get();

        return $this->render('vandaag', 'Vandaag', now()->translatedFormat('l j F') . ' · ' . $this->telling($taken, $kopen, $leren), [
            'secties' => array_filter([
                ['titel' => null,             'items' => $this->sorteer($taken)],
                ['titel' => 'Vandaag kopen',  'items' => $this->sorteer($kopen)],
                ['titel' => 'Leren',          'items' => $this->sorteer($leren)],
            ], fn ($s) => $s['items']->isNotEmpty()),
            'leegTekst' => 'Niks meer voor vandaag. Mooi.',
        ]);
    }

    public function binnenkort(): View
    {
        $items = $this->base()->upcoming(7)->get()->sortBy('due_date');

        $secties = $items
            ->groupBy(fn (Task $t) => $t->due_date->toDateString())
            ->map(fn (Collection $groep, string $datum) => [
                'titel' => \Illuminate\Support\Carbon::parse($datum)->translatedFormat('l j F'),
                'items' => $groep->values(),
            ])
            ->values()
            ->all();

        return $this->render('binnenkort', 'Binnenkort', 'de komende zeven dagen', [
            'secties'   => $secties,
            'leegTekst' => 'De komende week staat nog niets gepland.',
        ]);
    }

    public function kopen(): View
    {
        $items  = $this->sorteer($this->base()->ofType(Task::TYPE_KOPEN)->get());
        $totaal = $items->sum('price_cents');

        return $this->render('kopen', 'Kopen', $items->count() . ' dingen · samen € ' . number_format($totaal / 100, 2, ',', '.'), [
            'secties'   => [['titel' => null, 'items' => $items]],
            'leegTekst' => 'Niets te kopen op dit moment.',
            'nieuwType' => Task::TYPE_KOPEN,
        ]);
    }

    public function leren(): View
    {
        $items = $this->sorteer($this->base()->ofType(Task::TYPE_LEREN)->get());

        $secties = $items
            ->groupBy(fn (Task $t) => $t->kind ?: 'anders')
            ->map(fn (Collection $groep, string $soort) => [
                'titel' => $groep->count() > 0 ? TodoQuickParser::kindLabel($soort) : null,
                'items' => $groep->values(),
            ])
            ->values()
            ->all();

        return $this->render('leren', 'Leren', $items->count() . ' dingen om te doen of te volgen', [
            'secties'   => $secties,
            'leegTekst' => 'Nog niets op je leerlijst.',
            'nieuwType' => Task::TYPE_LEREN,
        ]);
    }

    public function crm(): View
    {
        $items = $this->sorteer($this->base()->where('source', Task::SOURCE_CRM)->get());

        return $this->render('crm', 'Uit het CRM', 'automatisch klaargezet · ' . $items->count() . ' open', [
            'secties'   => [['titel' => null, 'items' => $items]],
            'leegTekst' => 'Het CRM heeft niets voor je klaarstaan.',
            'geenInvoer' => true,
        ]);
    }

    public function lijst(TaskList $taskList): View
    {
        $items = $this->sorteer($this->base()->ofType(Task::TYPE_TAAK)->where('task_list_id', $taskList->id)->get());

        return $this->render('lijst', $taskList->title, $items->count() . ' open', [
            'secties'    => [['titel' => null, 'items' => $items]],
            'leegTekst'  => 'Deze lijst is leeg.',
            'lijstId'    => $taskList->id,
            'actieveLijst' => $taskList->id,
        ]);
    }

    public function klaar(): View
    {
        $items = Task::with('subtasks')
            ->topLevel()
            ->completed()
            ->orderByDesc('completed_at')
            ->limit(100)
            ->get();

        return $this->render('klaar', 'Klaar', 'de laatste 100 afgeronde dingen', [
            'secties'    => [['titel' => null, 'items' => $items]],
            'leegTekst'  => 'Nog niets afgevinkt.',
            'geenInvoer' => true,
        ]);
    }

    public function zoeken(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $items = $term === ''
            ? collect()
            : Task::with('subtasks')
                ->topLevel()
                ->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('notes', 'like', "%{$term}%"))
                ->orderByRaw('completed_at is not null')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();

        return $this->render('zoeken', 'Zoeken', $term === '' ? 'typ om te zoeken' : $items->count() . ' gevonden voor "' . $term . '"', [
            'secties'    => [['titel' => null, 'items' => $items]],
            'leegTekst'  => 'Niets gevonden.',
            'geenInvoer' => true,
            'zoekterm'   => $term,
        ]);
    }

    /**
     * Leads waarvan de opvolgdatum verlopen is.
     *
     * Bewust géén taken: dit is een directe weergave op de leads zelf, zodat
     * een notitie of nieuwe datum meteen in het CRM staat en er nooit twee
     * waarheden ontstaan.
     */
    public function opvolgen(): View
    {
        $leads = $this->verlopenLeads()
            ->with(['status', 'activities' => fn ($q) => $q->latest()->limit(2), 'activities.user'])
            ->orderBy('follow_up_at')
            ->get();

        $telaat = $leads->filter(fn (Lead $l) => $l->follow_up_at->isBefore(now()->startOfDay()))->count();

        return view('todo.opvolgen', array_merge($this->navData(), [
            'weergave'     => 'opvolgen',
            'titel'        => 'Opvolgen',
            'onderschrift' => $leads->count() . ' ' . ($leads->count() === 1 ? 'lead' : 'leads')
                              . ($telaat ? ", waarvan {$telaat} te laat" : ''),
            'leads'        => $leads,
            'geenInvoer'   => true,
        ]));
    }

    /** Notitie bij een lead — komt in het CRM terug in de tijdlijn van die lead */
    public function leadNotitie(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => auth()->id(),
            'activity_type' => 'note',
            'title'         => 'Notitie',
            'description'   => $data['note'],
        ]);

        return response()->json(['ok' => true]);
    }

    /** Nieuwe opvolgdatum (leeg = geen opvolging meer) */
    public function leadDatum(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'follow_up_at' => ['nullable', 'date'],
            'dagen'        => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $datum = isset($data['dagen'])
            ? now()->addDays($data['dagen'])->toDateString()
            : ($data['follow_up_at'] ?? null);

        $lead->update(['follow_up_at' => $datum]);

        return response()->json(['ok' => true, 'follow_up_at' => $datum]);
    }

    /** Contactmoment loggen, met dezelfde types als het CRM gebruikt */
    public function leadContact(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'contact_type' => ['required', 'in:' . implode(',', Lead::CONTACT_TYPES)],
            'note'         => ['nullable', 'string', 'max:2000'],
        ]);

        $labels = [
            'call_no_answer' => 'Gebeld — geen gehoor',
            'call_answered'  => 'Gebeld — gesproken',
            'email'          => 'Gemaild',
            'contact_other'  => 'Overig contact',
        ];

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => auth()->id(),
            'activity_type' => $data['contact_type'],
            'title'         => $labels[$data['contact_type']],
            'description'   => $data['note'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    // ── Lijsten ──────────────────────────────────────────────────────────────

    public function lijstOpslaan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $lijst = TaskList::create([
            'title'      => $data['title'],
            'color'      => $data['color'] ?? '#64748b',
            'user_id'    => auth()->id(),
            'sort_order' => (int) TaskList::max('sort_order') + 1,
        ]);

        return redirect()->route('todo.lijst', $lijst);
    }

    public function lijstBijwerken(Request $request, TaskList $taskList): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $taskList->update(['title' => $data['title'], 'color' => $data['color'] ?? $taskList->color]);

        return redirect()->route('todo.lijst', $taskList);
    }

    public function lijstVerwijderen(TaskList $taskList): RedirectResponse
    {
        $taskList->delete();

        return redirect()->route('todo.vandaag');
    }

    // ── Instellingen ─────────────────────────────────────────────────────────

    public function instellingen(): View
    {
        return view('todo.instellingen', array_merge($this->navData(), [
            'account'    => auth()->user()->account,
            'titel'      => 'Instellingen',
            'weergave'   => 'instellingen',
            'geenInvoer' => true,
        ]));
    }

    public function instellingenOpslaan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'offer_followup_days' => ['required', 'integer', 'min:1', 'max:60'],
            'gallery_days'        => ['required', 'integer', 'min:0', 'max:60'],
            'shift_days'          => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        auth()->user()->account->update(['todo_settings' => $data]);

        return redirect()->route('todo.instellingen')->with('bewaard', true);
    }

    // ── Items ────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:500'],
            'type'           => ['nullable', 'in:taak,kopen,leren'],
            'task_list_id'   => ['nullable', 'integer'],
            'parent_task_id' => ['nullable', 'integer'],
            'due_date'       => ['nullable', 'date'],
        ]);

        $type   = $data['type'] ?? Task::TYPE_TAAK;
        $parsed = $this->parser->parse($data['title'], $type);

        $list = isset($data['task_list_id'])
            ? TaskList::findOrFail($data['task_list_id'])
            : TaskList::defaultFor(auth()->user()->account_id);

        if (! empty($data['parent_task_id'])) {
            Task::findOrFail($data['parent_task_id']);
        }

        $task = Task::create(array_merge($parsed, [
            'task_list_id'   => $list->id,
            'parent_task_id' => $data['parent_task_id'] ?? null,
            'type'           => $type,
            'source'         => Task::SOURCE_HANDMATIG,
            'due_date'       => $data['due_date'] ?? $parsed['due_date'],
            'sort_order'     => (int) Task::where('task_list_id', $list->id)->max('sort_order') + 1,
        ]));

        return response()->json($this->payload($task));
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'title'        => ['sometimes', 'required', 'string', 'max:500'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:5000'],
            'due_date'     => ['sometimes', 'nullable', 'date'],
            'vendor'       => ['sometimes', 'nullable', 'string', 'max:120'],
            'url'          => ['sometimes', 'nullable', 'string', 'max:500'],
            'kind'         => ['sometimes', 'nullable', 'string', 'max:32'],
            'task_list_id' => ['sometimes', 'integer'],
            'price'        => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        if (array_key_exists('price', $data)) {
            $data['price_cents'] = $this->parser->bedragNaarCenten($data['price']);
            unset($data['price']);
        }

        if (isset($data['task_list_id'])) {
            TaskList::findOrFail($data['task_list_id']);
        }

        $task->update($data);

        return response()->json($this->payload($task->fresh()));
    }

    public function toggle(Task $task): JsonResponse
    {
        if ($task->completed_at) {
            $task->update(['completed_at' => null]);
        } else {
            $task->update(['completed_at' => now(), 'snoozed_until' => null]);
            $task->subtasks()->update(['completed_at' => now()]);
        }

        return response()->json([
            'completed' => $task->fresh()->is_completed,
        ]);
    }

    public function flag(Task $task): JsonResponse
    {
        $task->update(['flagged' => ! $task->flagged]);

        return response()->json(['flagged' => $task->fresh()->flagged]);
    }

    /** Wegleggen tot morgen (of een ander aantal dagen) zonder de deadline te verzetten */
    public function snooze(Request $request, Task $task): JsonResponse
    {
        $dagen = (int) $request->input('dagen', 1);
        $tot   = now()->addDays(max(1, $dagen))->toDateString();

        $task->update([
            'snoozed_until' => $tot,
            'due_date'      => $task->due_date && $task->due_date->lt(now()->addDays($dagen)->startOfDay())
                ? $tot
                : $task->due_date,
        ]);

        return response()->json(['snoozed_until' => $tot]);
    }

    /**
     * Weghalen. CRM-taken worden afgevinkt in plaats van verwijderd: anders
     * zou de volgende sync ze opnieuw aanmaken en blijft de app zeuren.
     */
    public function destroy(Task $task): JsonResponse
    {
        if ($task->signal_key) {
            $task->update([
                'completed_at' => now(),
                'notes'        => trim(($task->notes ?? '') . "\n\nHandmatig weggehaald op " . now()->format('d-m-Y') . '.'),
            ]);

            return response()->json(['ok' => true, 'afgevinkt' => true]);
        }

        $task->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer'],
        ])['items'];

        $accountId = auth()->user()->account_id;

        foreach ($items as $item) {
            Task::where('id', $item['id'])
                ->where('account_id', $accountId)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Intern ───────────────────────────────────────────────────────────────

    /** Open, niet weggelegde items op het hoogste niveau */
    private function base()
    {
        return Task::with('subtasks')->topLevel()->pending()->notSnoozed();
    }

    private function sorteer(Collection $items): Collection
    {
        return $items->sortBy([
            fn (Task $a, Task $b) => ($b->due_status === 'overdue' ? 1 : 0) <=> ($a->due_status === 'overdue' ? 1 : 0),
            fn (Task $a, Task $b) => ($b->flagged ? 1 : 0) <=> ($a->flagged ? 1 : 0),
            fn (Task $a, Task $b) => ($a->due_date?->timestamp ?? PHP_INT_MAX) <=> ($b->due_date?->timestamp ?? PHP_INT_MAX),
            fn (Task $a, Task $b) => $a->sort_order <=> $b->sort_order,
        ])->values();
    }

    private function telling(Collection ...$groepen): string
    {
        $totaal = array_sum(array_map(fn (Collection $g) => $g->count(), $groepen));
        $telaat = $groepen[0]->where('due_status', 'overdue')->count();

        if ($totaal === 0) return 'niets te doen';

        $tekst = $totaal . ' ' . ($totaal === 1 ? 'ding' : 'dingen');

        return $telaat > 0 ? "{$tekst}, waarvan {$telaat} te laat" : $tekst;
    }

    private function render(string $weergave, string $titel, string $onderschrift, array $extra): View
    {
        return view('todo.index', array_merge($this->navData(), [
            'weergave'     => $weergave,
            'titel'        => $titel,
            'onderschrift' => $onderschrift,
        ], $extra));
    }

    /** Tellers en lijsten voor de linkerkolom */
    private function navData(): array
    {
        $vandaag = now()->toDateString();

        return [
            // Badge telt alleen taken: Kopen en Leren hebben hun eigen menu-item
            'lijsten'     => TaskList::withCount(['tasks as open_count' => fn ($q) => $q
                                    ->whereNull('completed_at')
                                    ->where('type', Task::TYPE_TAAK)])
                                ->orderBy('sort_order')->orderBy('id')->get(),
            'telVandaag'  => (clone $this->base())->where(fn ($q) => $q->whereDate('due_date', '<=', $vandaag)->orWhere('flagged', true))->count(),
            'telTeLaat'   => (clone $this->base())->whereDate('due_date', '<', $vandaag)->count(),
            'telBinnenkort' => (clone $this->base())->upcoming(7)->count(),
            'telKopen'    => (clone $this->base())->ofType(Task::TYPE_KOPEN)->count(),
            'somKopen'    => (int) (clone $this->base())->ofType(Task::TYPE_KOPEN)->sum('price_cents'),
            'telLeren'    => (clone $this->base())->ofType(Task::TYPE_LEREN)->count(),
            'telCrm'      => (clone $this->base())->where('source', Task::SOURCE_CRM)->count(),
            'telKlaar'    => Task::topLevel()->completed()->count(),
            'telOpvolgen' => $this->verlopenLeads()->count(),
        ];
    }

    /** Leads met een opvolgdatum van vandaag of eerder, niet gearchiveerd */
    private function verlopenLeads()
    {
        return Lead::whereNull('archived_at')
            ->whereNotNull('follow_up_at')
            ->whereDate('follow_up_at', '<=', now()->toDateString());
    }

    private function payload(Task $task): array
    {
        return array_merge($task->toArray(), [
            'price_label' => $task->price_label,
            'source_url'  => $task->sourceUrl(),
            'source_label' => $task->sourceLabel(),
        ]);
    }
}
