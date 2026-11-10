<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Belief;
use App\Models\Fundamental;
use App\Models\Mission;
use App\Models\Problem;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Fundamentals;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The Fundamentals (PRD D-01 to D-04). */
class FundamentalsController extends Controller
{
    public function show(Request $request, Fundamentals $svc, Access $access, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $version = $request->query('version') ? Fundamental::where('status', 'published')->where('version', (int) $request->query('version'))->firstOrFail() : $svc->latest();
        $cal = Calendar::for($tenant->organization);
        $leadSeat = $access->formation()->leadershipSeatId();

        return view('fundamentals.show', [
            'f' => $version, 'latest' => $svc->latest(), 'page' => $request->query('page') === 'plan' ? 'plan' : 'vision',
            'canEdit' => $access->leadership(), 'cal' => $cal,
            'missions' => Mission::with('owner.user')->where('level', 'company')->whereIn('status', ['on_track', 'off_track'])->where('quarter_start', $cal->currentQuarterStart()->toDateString())->get(),
            'longProblems' => $leadSeat ? Problem::where('team_seat_id', $leadSeat)->where('horizon', 'long')->whereIn('status', ['open', 'solving'])->get() : collect(),
        ]);
    }

    public function edit(Fundamentals $svc, Tenant $tenant)
    {
        $this->authorize('leadership', $tenant->organization);
        $draft = $svc->draft();
        $beliefs = $svc->activeBeliefs();

        return view('fundamentals.edit', ['draft' => $draft, 'c' => $draft->content, 'beliefs' => $beliefs, 'problems' => session('publish_problems', []),
            'archived' => Belief::whereNotNull('archived_at')->orderBy('name')->get()]);
    }

    public function save(Request $request, Fundamentals $svc, Tenant $tenant)
    {
        $this->authorize('leadership', $tenant->organization);
        $request->validate([
            'why' => 'nullable|string|max:300', 'way' => 'nullable|string|max:300', 'objective_statement' => 'nullable|string|max:200',
            'objective_year' => 'nullable|integer|between:2000,2200', 'target_market' => 'nullable|string|max:1000', 'uniques' => 'array|max:3',
            'uniques.*' => 'nullable|string|max:120', 'proven_process' => 'nullable|string|max:5000', 'guarantee' => 'nullable|string|max:300',
            'snapshot_date' => 'nullable|date', 'snapshot_revenue' => 'nullable|string|max:60', 'snapshot_profit' => 'nullable|string|max:60',
            'snapshot_measurables' => 'nullable|string|max:2000', 'picture' => 'nullable|string|max:5000',
            'annual_date' => 'nullable|date', 'annual_revenue' => 'nullable|string|max:60', 'annual_profit' => 'nullable|string|max:60',
            'annual_measurables' => 'nullable|string|max:2000', 'goals' => 'nullable|string|max:3000',
        ]);
        $draft = $svc->draft();
        $draft->content = Fundamentals::fromForm($request->all());
        $draft->save();
        if ($request->expectsJson()) {
            return response()->json(['saved' => true]);
        }
        if ($request->input('publish')) {
            $problems = $svc->problems($draft->content, $svc->activeBeliefs());
            if ($problems) {
                return redirect()->route('org.fundamentals.edit')->with('publish_problems', $problems)->withErrors(['publish' => 'Fix the items marked below, then publish again.']);
            }
            $v = $svc->publish($draft);

            return redirect()->route('org.fundamentals.show')->with('status', 'Published version '.$v->version);
        }

        return back()->with('status', 'Saved');
    }

    public function beliefStore(Request $request, Tenant $tenant)
    {
        $this->authorize('leadership', $tenant->organization);
        $d = $request->validate(['name' => 'required|string|min:2|max:80', 'description' => 'nullable|string|max:400']);
        if (Belief::whereNull('archived_at')->count() >= 7) {
            throw ValidationException::withMessages(['name' => 'The Belief System holds at most 7 beliefs. Archive one first.']);
        }
        Belief::create($d + ['sort_order' => (int) Belief::max('sort_order') + 1]);

        return back()->with('status', 'Belief added');
    }

    public function beliefUpdate(Request $request, Belief $belief, Tenant $tenant)
    {
        $this->authorize('leadership', $tenant->organization);
        $d = $request->validate(['name' => 'nullable|string|min:2|max:80', 'description' => 'nullable|string|max:400', 'move' => 'nullable|in:up,down', 'archive' => 'nullable|boolean']);
        if ($d['move'] ?? null) {
            $list = Belief::whereNull('archived_at')->orderBy('sort_order')->orderBy('id')->get()->values();
            $i = $list->search(fn ($b) => $b->id === $belief->id);
            $j = $d['move'] === 'up' ? $i - 1 : $i + 1;
            if ($i !== false && isset($list[$j])) {
                $order = $list->all();
                [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
                foreach ($order as $k => $b) {
                    $b->sort_order = $k;
                    $b->save();
                }
            }
        } elseif (isset($d['archive'])) {
            if (! $d['archive'] && Belief::whereNull('archived_at')->count() >= 7) {
                throw ValidationException::withMessages(['name' => 'The Belief System holds at most 7 beliefs. Archive one first.']);
            }
            $belief->archived_at = $d['archive'] ? now() : null;
            $belief->save();
        } else {
            $belief->fill(array_filter(['name' => $d['name'] ?? null, 'description' => $d['description'] ?? null], fn ($v) => $v !== null))->save();
        }

        return back()->with('status', 'Saved');
    }

    public function history(Request $request, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $versions = Fundamental::with('publisher.user')->where('status', 'published')->orderByDesc('version')->get();
        $a = $versions->firstWhere('version', (int) $request->query('a'));
        $b = $versions->firstWhere('version', (int) $request->query('b'));

        return view('fundamentals.history', ['versions' => $versions, 'a' => $a, 'b' => $b]);
    }
}
