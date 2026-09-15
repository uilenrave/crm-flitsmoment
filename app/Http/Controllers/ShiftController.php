<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Shift;
use App\Models\ShiftJob;
use App\Models\Staff;
use App\Scopes\AccountScope;
use App\Services\ShiftRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ShiftController extends Controller
{
    private function accountId(): int
    {
        return (int) auth()->user()->account_id;
    }

    /** Planner: datumkiezer, nog-in-te-plannen klussen en de shifts van die dag. */
    public function index(Request $request): View
    {
        $date = $request->query('date') ?: now()->toDateString();
        $date = Carbon::parse($date)->toDateString();

        $board = $this->boardData($date);

        $mapsKey = config('services.google.maps_key');
        $month   = $request->query('month') ?: substr($date, 0, 7);

        if ($request->boolean('partial')) {
            return view('shifts._board', $board + ['date' => $date, 'mapsKey' => $mapsKey]);
        }

        if ($request->boolean('calendar')) {
            return view('shifts._calendar', $this->monthData($month, $date) + ['date' => $date]);
        }

        return view('shifts.index', $board + [
            'date'     => $date,
            'mapsKey'  => $mapsKey,
            'month'    => $month,
            'calendar' => $this->monthData($month, $date),
        ]);
    }

    /** Data voor het planbord: shifts (met verse route) + nog niet ingeplande klussen. */
    private function boardData(string $date): array
    {
        $accountId = $this->accountId();

        $shifts = Shift::where('shift_date', $date)
            ->with(['staff', 'staff2', 'jobs.booking', 'account'])
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        // Route verversen (goedkoop: reistijden komen uit cache tenzij de route wijzigde)
        $service = app(ShiftRouteService::class);
        foreach ($shifts as $shift) {
            $shift->recompute($service);
        }

        $unplanned = $this->momentsForDate($date, $accountId)
            ->filter(fn ($m) => $m['shift_job'] === null)
            ->values();

        $staffMembers = Staff::where('is_active', true)->orderBy('name')->get();

        return ['shifts' => $shifts, 'unplanned' => $unplanned, 'staffMembers' => $staffMembers];
    }

    /**
     * Alle logistieke momenten (boeking + actie) waarvan de vereiste tijd op $date valt.
     * Elk moment weet of het al in een shift zit.
     */
    private function momentsForDate(string $date, int $accountId)
    {
        $bookings = Booking::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $accountId)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($date) {
                $q->whereDate('delivery_at', $date)
                  ->orWhereDate('pickup_at', $date)
                  ->orWhereDate('customer_pickup_at', $date)
                  ->orWhereDate('customer_return_at', $date);
            })
            ->with(['shiftJobs', 'deliveryStaff', 'deliveryStaff2', 'pickupStaff', 'pickupStaff2'])
            ->get();

        $moments = collect();
        foreach ($bookings as $b) {
            foreach (ShiftJob::ACTIONS as $action => $meta) {
                if ($meta['booking_type'] !== $b->booking_type) {
                    continue;
                }
                $t = $b->{$meta['time_field']};
                if (! $t || $t->toDateString() !== $date) {
                    continue;
                }
                $moments->push([
                    'booking'     => $b,
                    'action'      => $action,
                    'meta'        => $meta,
                    'required_at' => $t,
                    'shift_job'   => $b->shiftJobs->firstWhere('action', $action),
                ]);
            }
        }

        return $moments->sortBy('required_at')->values();
    }

    /** Maandoverzicht: per dag het aantal shifts en het aantal nog-in-te-plannen klussen. */
    private function monthData(string $month, string $selected): array
    {
        $accountId = $this->accountId();
        $first = Carbon::parse($month . '-01')->startOfMonth();
        $last  = $first->copy()->endOfMonth();

        $shiftsByDay = Shift::where('account_id', $accountId)
            ->whereBetween('shift_date', [$first->toDateString(), $last->toDateString()])
            ->withCount('jobs')
            ->get()
            ->groupBy(fn ($s) => $s->shift_date->toDateString());

        $bookings = Booking::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $accountId)
            ->whereIn('status', ['confirmed', 'completed'])
            ->where(function ($q) use ($first, $last) {
                foreach (['delivery_at', 'pickup_at', 'customer_pickup_at', 'customer_return_at'] as $f) {
                    $q->orWhereBetween($f, [$first, $last]);
                }
            })
            ->with('shiftJobs')
            ->get();

        $openByDay = [];
        foreach ($bookings as $b) {
            foreach (ShiftJob::ACTIONS as $action => $meta) {
                if ($meta['booking_type'] !== $b->booking_type) {
                    continue;
                }
                $t = $b->{$meta['time_field']};
                if (! $t || $t->lt($first) || $t->gt($last)) {
                    continue;
                }
                if ($b->shiftJobs->firstWhere('action', $action)) {
                    continue;   // al in een shift
                }
                $d = $t->toDateString();
                $openByDay[$d] = ($openByDay[$d] ?? 0) + 1;
            }
        }

        // Grid: maandag t/m zondag, hele weken rond de maand
        $cursor  = $first->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $last->copy()->endOfWeek(Carbon::SUNDAY);
        $days = [];
        while ($cursor <= $gridEnd) {
            $ds        = $cursor->toDateString();
            $dayShifts = $shiftsByDay->get($ds, collect());
            $days[] = [
                'date'     => $ds,
                'day'      => $cursor->day,
                'in_month' => $cursor->month === $first->month,
                'selected' => $ds === $selected,
                'today'    => $cursor->isToday(),
                'shifts'   => $dayShifts->filter(fn ($s) => $s->jobs_count > 0)->count(),
                'open'     => $openByDay[$ds] ?? 0,
            ];
            $cursor->addDay();
        }

        return [
            'cal_days'  => $days,
            'cal_label' => $first->translatedFormat('F Y'),
            'cal_prev'  => $first->copy()->subMonthNoOverflow()->format('Y-m'),
            'cal_next'  => $first->copy()->addMonthNoOverflow()->format('Y-m'),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shift_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
        ]);

        $account = auth()->user()->account;
        $date    = Carbon::parse($data['shift_date'])->toDateString();

        $shift = Shift::create([
            'account_id'    => $account->id,
            'shift_date'    => $date,
            'start_at'      => ! empty($data['start_time']) ? Carbon::parse($date . ' ' . $data['start_time']) : null,
            'start_manual'  => ! empty($data['start_time']),
            'start_address' => Shift::DEPOT_ADDRESS,
        ]);

        return response()->json(['ok' => true, 'shift_id' => $shift->id]);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $data = $request->validate([
            'staff_id'      => ['nullable', 'exists:staff,id'],
            'staff_id_2'    => ['nullable', 'exists:staff,id'],
            'start_time'    => ['nullable', 'date_format:H:i'],
            'start_manual'  => ['nullable', 'boolean'],
            'start_address' => ['nullable', 'string', 'max:255'],
            'end_address'   => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ]);

        $shift->staff_id      = $data['staff_id'] ?? null;
        // 2e persoon mag niet dezelfde zijn als de 1e, en niet zonder 1e persoon
        $staff2 = $data['staff_id_2'] ?? null;
        $shift->staff_id_2    = ($staff2 && $staff2 != $shift->staff_id) ? $staff2 : null;
        $shift->start_address = $data['start_address'] ?? null;
        $shift->end_address   = ($data['end_address'] ?? null) ?: null;
        $shift->notes         = $data['notes'] ?? null;

        // Starttijd: alleen aanraken als de client dit expliciet meestuurt (start_manual).
        // true = handmatige tijd vastzetten; false = terug naar automatisch terugrekenen.
        if ($request->has('start_manual')) {
            $manual = $request->boolean('start_manual');
            $shift->start_manual = $manual;
            $shift->start_at = ($manual && ! empty($data['start_time']))
                ? Carbon::parse($shift->shift_date->toDateString() . ' ' . $data['start_time'])
                : null;
        }
        $shift->save();

        // Bridge: shift-medewerker doorzetten naar de oude staff-velden
        $shift->load('jobs.booking');
        $shift->syncLegacyStaffAssignments();
        $shift->recompute();

        return response()->json(['ok' => true]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $shift->load('jobs.booking');
        foreach ($shift->jobs as $job) {
            $job->setRelation('shift', $shift);
            $job->clearLegacyStaff();
        }
        $shift->jobs()->delete();
        $shift->delete();

        return response()->json(['ok' => true]);
    }

    /** Koppel een klus (boeking + actie) aan een shift. */
    public function attachJob(Request $request, Shift $shift): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['nullable', 'integer'],
            'action'     => ['required', 'in:' . implode(',', array_keys(ShiftJob::ACTIONS))],
        ]);

        $isLaden = $data['action'] === 'laden';
        if (! $isLaden && empty($data['booking_id'])) {
            return response()->json(['ok' => false, 'error' => 'Geen boeking opgegeven.'], 422);
        }

        // Boeking-klussen mogen nog niet in een shift zitten (Laden mag meerdere keren)
        if (! $isLaden) {
            $exists = ShiftJob::where('booking_id', $data['booking_id'])->where('action', $data['action'])->exists();
            if ($exists) {
                return response()->json(['ok' => false, 'error' => 'Deze klus zit al in een shift.'], 422);
            }
        }

        $durations = auth()->user()->account->shiftDurations();
        $maxOrder  = (int) $shift->jobs()->max('sort_order');

        $job = ShiftJob::create([
            'shift_id'         => $shift->id,
            'booking_id'       => $data['booking_id'] ?? null,
            'action'           => $data['action'],
            'sort_order'       => $maxOrder + 1,
            'duration_minutes' => $durations[$data['action']] ?? 15,
        ]);

        $job->setRelation('shift', $shift);
        $job->syncLegacyStaff();

        $shift->load('jobs.booking');
        $shift->recompute();

        return response()->json(['ok' => true]);
    }

    public function detachJob(ShiftJob $job): JsonResponse
    {
        $shift = $job->shift;
        abort_if(! $shift || $shift->account_id !== $this->accountId(), 404);
        $job->setRelation('shift', $shift);
        $job->clearLegacyStaff();
        $job->delete();

        $shift->load('jobs.booking');
        $shift->recompute();

        return response()->json(['ok' => true]);
    }

    /** Herorden de klussen binnen een shift. */
    public function reorder(Request $request, Shift $shift): JsonResponse
    {
        $data = $request->validate([
            'job_ids'   => ['required', 'array'],
            'job_ids.*' => ['integer'],
        ]);

        foreach (array_values($data['job_ids']) as $i => $jobId) {
            ShiftJob::where('id', $jobId)->where('shift_id', $shift->id)->update(['sort_order' => $i]);
        }

        $shift->load('jobs.booking');
        $shift->recompute();

        return response()->json(['ok' => true]);
    }

    /** Pas de werktijd van één klus aan. */
    public function updateJob(Request $request, ShiftJob $job): JsonResponse
    {
        $data = $request->validate([
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);

        $shift = $job->shift;
        abort_if(! $shift || $shift->account_id !== $this->accountId(), 404);

        $job->update(['duration_minutes' => $data['duration_minutes']]);
        $shift->load('jobs.booking');
        $shift->recompute();

        return response()->json(['ok' => true]);
    }

    /** Forceer een verse route-berekening (negeer de reistijd-cache). */
    public function recompute(Shift $shift): JsonResponse
    {
        $shift->route_cache = null;   // dwingt Google opnieuw af
        $shift->load('jobs.booking');
        $shift->recompute();

        return response()->json(['ok' => true, 'total_minutes' => $shift->total_minutes]);
    }
}
