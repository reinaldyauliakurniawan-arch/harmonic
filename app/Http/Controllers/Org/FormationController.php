<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\Procedure;
use App\Models\Seat;
use App\Services\Access;
use App\Services\Tasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Winning Formation: the seat tree (PRD W-01 to W-05). */
class FormationController extends Controller
{
    public function index(Organization $organization, Access $access)
    {
        $this->authorize('viewAny', Seat::class);
        $f = $access->formation();

        return view('formation.index', [
            'f' => $f,
            'leadership' => $f->leadershipSeatIds(),
            'canManage' => $access->orgAdmin(),
            'members' => Tasks::memberOptions(),
        ]);
    }

    private function validated(Request $request, ?Seat $seat = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|min:2|max:120',
            'parent_id' => $seat ? 'prohibited' : 'required|integer',
            'responsibilities' => 'nullable|string|max:2000',
            'team_name' => 'nullable|string|max:60',
            'is_leadership_team' => 'boolean',
        ]);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['responsibilities'] ?? '')), 'strlen'));
        if (count($lines) > 7) {
            throw ValidationException::withMessages(['responsibilities' => 'A seat has at most 7 responsibilities.']);
        }
        foreach ($lines as $l) {
            if (mb_strlen($l) < 3 || mb_strlen($l) > 120) {
                throw ValidationException::withMessages(['responsibilities' => 'Each responsibility needs 3 to 120 characters.']);
            }
        }
        $data['responsibilities'] = $lines;

        return $data;
    }

    public function create(Request $request, Access $access)
    {
        $this->authorize('manage', Seat::class);

        return view('formation.form', ['seat' => null, 'f' => $access->formation(), 'parent' => (int) $request->query('parent')]);
    }

    public function store(Request $request, Access $access)
    {
        $this->authorize('manage', Seat::class);
        $data = $this->validated($request);
        $parent = Seat::findOrFail($data['parent_id']);
        $seat = new Seat(['title' => $data['title'], 'responsibilities' => $data['responsibilities']]);
        $seat->parent_id = $parent->id;
        $seat->sort_order = (int) Seat::where('parent_id', $parent->id)->max('sort_order') + 1;
        $seat->save();
        $access->refresh();

        return redirect()->route('org.formation.index')->with('status', 'Seat added');
    }

    public function edit(Seat $seat, Access $access)
    {
        $this->authorize('manage', $seat);

        return view('formation.form', ['seat' => $seat, 'f' => $access->formation(), 'parent' => $seat->parent_id, 'members' => Tasks::memberOptions()]);
    }

    public function update(Request $request, Seat $seat, Access $access)
    {
        $this->authorize('manage', $seat);
        $data = $this->validated($request, $seat);
        $hasChildren = $access->formation()->isTeam($seat->id);
        DB::transaction(function () use ($seat, $data, $hasChildren) {
            $seat->title = $data['title'];
            $seat->responsibilities = $data['responsibilities'];
            $seat->team_name = $hasChildren ? ($data['team_name'] ?? null ?: null) : $seat->team_name;
            if ($hasChildren && ($data['is_leadership_team'] ?? false) && ! $seat->is_leadership_team) {
                Seat::where('is_leadership_team', true)->update(['is_leadership_team' => false]);
                $seat->is_leadership_team = true;
            }
            $seat->save();
        });
        $access->refresh();

        return redirect()->route('org.formation.index')->with('status', 'Saved');
    }

    /** W-03: filling a vacant seat needs no Handover; replacing or vacating goes through it. */
    public function holder(Request $request, Seat $seat, Access $access)
    {
        $this->authorize('manage', $seat);
        $data = $request->validate(['holder_id' => 'nullable|integer']);
        $holder = $data['holder_id'] ? Membership::active()->findOrFail($data['holder_id'])->id : null;
        if ($holder === $seat->holder_id) {
            return back();
        }
        if ($seat->holder_id) {
            return redirect()->route('org.handover.show', ['type' => 'vacate', 'seat' => $seat->id, 'holder' => $holder, 'back' => route('org.formation.index')]);
        }
        $seat->holder_id = $holder;
        $seat->save();
        ActivityLog::record('seat.assigned', $seat, ['member' => $holder]);
        $access->refresh();

        return back()->with('status', 'Seat assigned');
    }

    public function move(Request $request, Seat $seat)
    {
        $this->authorize('manage', $seat);
        $data = $request->validate(['parent_id' => 'required|integer']);

        return redirect()->route('org.handover.show', ['type' => 'move', 'seat' => $seat->id, 'parent' => $data['parent_id'], 'back' => route('org.formation.index')]);
    }

    public function destroy(Seat $seat)
    {
        $this->authorize('manage', $seat);

        return redirect()->route('org.handover.show', ['type' => 'delete_seat', 'seat' => $seat->id, 'back' => route('org.formation.index')]);
    }

    /** W-02: the order of the seats directly under $seat, sent by drag and drop or the arrow buttons. */
    public function order(Request $request, Seat $seat, Access $access)
    {
        $this->authorize('manage', $seat);
        $ids = array_map('intval', $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids']);
        $children = $access->formation()->childIds($seat->id);
        abort_unless(count($ids) === count($children) && ! array_diff($ids, $children), 422);
        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                Seat::whereKey($id)->update(['sort_order' => $i]);
            }
        });

        return $request->expectsJson() ? response()->json(['saved' => true]) : back()->with('status', 'Order saved');
    }

    /** W-08 helper: what a vacant seat owns. */
    public static function ownedCounts(array $seatIds): array
    {
        return [
            'metrics' => Metric::whereNull('archived_at')->whereIn('owner_seat_id', $seatIds)->selectRaw('owner_seat_id, count(*) c')->groupBy('owner_seat_id')->pluck('c', 'owner_seat_id')->all(),
            'procedures' => Procedure::whereNull('archived_at')->whereIn('owner_seat_id', $seatIds)->selectRaw('owner_seat_id, count(*) c')->groupBy('owner_seat_id')->pluck('c', 'owner_seat_id')->all(),
        ];
    }
}
