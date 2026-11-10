<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use App\Services\Access;
use App\Services\Handover;
use Illuminate\Http\Request;

/** The one Handover dialog every trigger goes through (PRD section 8). */
class HandoverController extends Controller
{
    private function authorizeTrigger(array $t, Organization $org, Access $access): void
    {
        if ($t['type'] === 'account') {
            abort_unless($t['member'] === $access->id(), 403);
        } else {
            $this->authorize('manage', $org);
        }
    }

    public function show(Request $request, Organization $organization, Handover $handover, Access $access)
    {
        $t = $handover->validateTrigger($request->query());
        $this->authorizeTrigger($t, $organization, $access);
        $f = $access->formation();
        $dissolved = $handover->dissolvedTeam($t);

        return view('org.handover', [
            't' => $t,
            'plan' => $handover->plan($t),
            'members' => Membership::active()->with('user')->get()->sortBy('name'),
            'f' => $f,
            'dissolved' => $dissolved,
            'dissolution' => $dissolved ? $handover->dissolutionCounts($dissolved) : [],
            'owned' => $t['type'] === 'delete_seat' ? $handover->ownedBySeat($t['seat']) : [],
            'cancel' => $request->query('back') && str_starts_with($request->query('back'), url('/')) ? $request->query('back') : route('org.home'),
        ]);
    }

    public function store(Request $request, Organization $organization, Handover $handover, Access $access)
    {
        $t = $handover->validateTrigger($request->input('trigger', []));
        $this->authorizeTrigger($t, $organization, $access);
        $request->validate(['receivers' => 'array', 'receivers.*.all' => 'nullable|integer', 'receivers.*.items' => 'array', 'receivers.*.items.*' => 'nullable|integer']);
        $handover->execute($t, $request->input('receivers', []));

        if ($t['type'] === 'account') {
            return redirect()->route('profile.edit')->with('status', 'You left '.$organization->name);
        }

        return redirect($request->input('back') && str_starts_with($request->input('back'), url('/')) ? $request->input('back') : route('org.formation.index'))->with('status', 'Handover completed');
    }
}
