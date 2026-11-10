<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use App\Models\ProcedureVersion;
use App\Models\SyncAudit;
use App\Services\Access;
use App\Services\Calendar;
use App\Services\Procedures;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Standardization and the Synchronization Cycle (PRD PR-01 to SY-04). */
class ProcedureController extends Controller
{
    public function index(Access $access, Procedures $svc, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $f = $access->formation();
        $today = Calendar::for($tenant->organization)->today();
        $list = Procedure::with('ownerSeat.holder.user')->whereNull('archived_at')->orderBy('title')->get();

        return view('procedures.index', [
            'groups' => $list->groupBy('team_seat_id'), 'teams' => $f->teamOptions(), 'count' => $list->count(),
            'status' => $list->mapWithKeys(fn ($p) => [$p->id => $svc->status($p, $f, $today)]),
            'creatable' => collect($f->teamOptions())->filter(fn ($l, $id) => auth()->user()->can('create', [Procedure::class, $id]))->all(),
            'archived' => Procedure::whereNotNull('archived_at')->orderBy('title')->get(),
        ]);
    }

    public function sync(Request $request, Access $access, Procedures $svc, Tenant $tenant)
    {
        $this->authorize('view', $tenant->organization);
        $f = $access->formation();
        $today = Calendar::for($tenant->organization)->today();
        $rows = Procedure::whereNull('archived_at')->get()->map(fn ($p) => ['p' => $p, 's' => $svc->status($p, $f, $today)]);
        $rows = $request->query('sort') === 'status' ? $rows->sortBy(fn ($r) => $r['s']['in_sync'] ? 1 : 0) : $rows->sortBy(fn ($r) => $r['p']->title);

        return view('procedures.sync', ['rows' => $rows->values(), 'teams' => $f->teamOptions(), 'sort' => $request->query('sort')]);
    }

    private function rules(): array
    {
        return [
            'title' => 'required|string|min:2|max:160', 'purpose' => 'required|string|min:10|max:500', 'owner_seat_id' => 'required|integer',
            'audience' => 'required|in:team,organization', 'review_every_months' => 'required|in:3,6,12', 'body' => 'nullable|string|max:60000',
        ];
    }

    private function checkOwner(Access $access, int $team, int $seat): void
    {
        if (! in_array($seat, $access->formation()->teamSeatIds($team), true)) {
            throw ValidationException::withMessages(['owner_seat_id' => 'Choose a seat in the owning team.']);
        }
    }

    public function create(Request $request, Access $access)
    {
        $team = (int) $request->query('team');
        $this->authorize('create', [Procedure::class, $team]);

        return view('procedures.edit', ['p' => null, 'team' => $team, 'f' => $access->formation()]);
    }

    public function store(Request $request, Access $access)
    {
        $team = (int) $request->input('team_seat_id');
        $this->authorize('create', [Procedure::class, $team]);
        $d = $request->validate($this->rules());
        $this->checkOwner($access, $team, (int) $d['owner_seat_id']);
        $p = new Procedure($d + ['team_seat_id' => $team]);
        $p->save();

        return redirect()->route('org.procedures.edit', $p)->with('status', 'Draft saved. Write the steps next');
    }

    public function edit(Procedure $procedure, Access $access)
    {
        $this->authorize('update', $procedure);

        return view('procedures.edit', ['p' => $procedure, 'team' => $procedure->team_seat_id, 'f' => $access->formation()]);
    }

    public function update(Request $request, Procedure $procedure, Access $access, Procedures $svc)
    {
        $this->authorize('update', $procedure);
        $d = $request->validate($this->rules());
        $this->checkOwner($access, $procedure->team_seat_id, (int) $d['owner_seat_id']);
        $procedure->fill($d)->save();
        if ($request->input('publish')) {
            if (trim((string) $procedure->body) === '') {
                throw ValidationException::withMessages(['body' => 'Write the procedure before publishing.']);
            }
            $v = $svc->publish($procedure, $access->id());

            return redirect()->route('org.procedures.show', $procedure)->with('status', 'Published v'.$v->version);
        }

        return back()->with('status', 'Draft saved');
    }

    public function show(Request $request, Procedure $procedure, Access $access, Procedures $svc, Tenant $tenant)
    {
        $this->authorize('view', $procedure);
        $f = $access->formation();
        $versions = ProcedureVersion::with('publisher.user')->where('procedure_id', $procedure->id)->orderByDesc('version')->get();
        $shown = $request->query('v') ? $versions->firstWhere('version', (int) $request->query('v')) : $versions->first();
        $today = Calendar::for($tenant->organization)->today();
        $me = $access->id();

        return view('procedures.show', [
            'p' => $procedure->load('ownerSeat.holder.user'), 'versions' => $versions, 'shown' => $shown, 'status' => $svc->status($procedure, $f, $today),
            'audits' => SyncAudit::with('auditor.user')->where('procedure_id', $procedure->id)->orderByDesc('audited_on')->orderByDesc('id')->limit(25)->get(),
            'inAudience' => $svc->inAudience($procedure, $me, $f),
            'acked' => DB::table('procedure_acks')->where('procedure_id', $procedure->id)->where('version', $procedure->version)->where('member_id', $me)->exists(),
            'teams' => $f->teamOptions(), 'today' => $today,
        ]);
    }

    public function acknowledge(Procedure $procedure, Access $access, Procedures $svc)
    {
        $this->authorize('view', $procedure);
        abort_unless($procedure->version > 0 && $svc->inAudience($procedure, $access->id(), $access->formation()), 403);
        DB::table('procedure_acks')->insertOrIgnore(['procedure_id' => $procedure->id, 'version' => $procedure->version, 'member_id' => $access->id(), 'acknowledged_at' => now()]);

        return back()->with('status', 'Acknowledged');
    }

    public function audit(Request $request, Procedure $procedure, Access $access, Tenant $tenant)
    {
        $this->authorize('audit', $procedure);
        $today = Calendar::for($tenant->organization)->today()->toDateString();
        $d = $request->validate(['audited_on' => 'required|date|before_or_equal:'.$today, 'result' => 'required|in:followed,partly,not_followed',
            'findings' => 'nullable|required_unless:result,followed|string|max:2000'],
            ['audited_on.before_or_equal' => "The audit date can't be in the future.", 'findings.required_unless' => 'Describe what you found.']);
        $a = new SyncAudit($d);
        $a->procedure_id = $procedure->id;
        $a->procedure_version = $procedure->version;
        $a->auditor_id = $access->id();
        $a->save();

        return back()->with('status', 'Audit recorded')->with('audit_followup', $a->result !== 'followed' ? $a->id : null);
    }

    /** SY-02: "Still current" resets the review clock. */
    public function review(Procedure $procedure, Tenant $tenant)
    {
        $this->authorize('update', $procedure);
        abort_unless($procedure->version > 0, 422);
        $procedure->reviewed_at = Calendar::for($tenant->organization)->today()->toDateString();
        $procedure->save();

        return back()->with('status', 'Marked as still current');
    }

    public function archive(Procedure $procedure)
    {
        $this->authorize('archive', $procedure);
        $procedure->archived_at = $procedure->archived_at ? null : now();
        $procedure->save();

        return redirect()->route('org.procedures.index')->with('status', $procedure->archived_at ? 'Archived' : 'Restored');
    }

    public function preview(Request $request, Procedure $procedure)
    {
        $this->authorize('update', $procedure);

        return response()->json(['html' => Procedures::render($request->input('body')), 'words' => Procedures::words($request->input('body'))]);
    }
}
