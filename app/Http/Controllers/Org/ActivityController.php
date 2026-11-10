<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public const ACTIONS = ['member.invited', 'member.joined', 'member.rejoined', 'member.departed', 'member.role_changed',
        'seat.assigned', 'seat.vacated', 'seat.moved', 'seat.deleted', 'handover.completed', 'org.settings_changed', 'metric.goal_reapplied'];

    public function index(Request $request, Organization $organization)
    {
        $this->authorize('manage', $organization);
        $q = $request->validate(['person' => 'nullable|integer', 'action' => 'nullable|in:'.implode(',', self::ACTIONS)]);
        $logs = ActivityLog::with('actor.user')
            ->when($q['person'] ?? null, fn ($w, $p) => $w->where('actor_id', $p))
            ->when($q['action'] ?? null, fn ($w, $a) => $w->where('action', $a))
            ->latest('created_at')->latest('id')->paginate(25)->withQueryString();
        $people = Membership::with('user')->get()->sortBy('name')->mapWithKeys(fn ($m) => [$m->id => $m->name]);
        $names = Membership::with('user')->get()->mapWithKeys(fn ($m) => [$m->id => $m->name]);

        return view('org.activity', compact('logs', 'people', 'names', 'q'));
    }
}
