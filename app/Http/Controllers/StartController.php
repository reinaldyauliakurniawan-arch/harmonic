<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Where a signed-in user lands (PRD F-05). */
class StartController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $active = $user->activeMemberships();
        if ($active->count() === 1) {
            return redirect()->route('org.home', ['organization' => $active->first()->organization->slug]);
        }
        $pending = $user->memberships()->where('status', 'active')->with('organization')->get()
            ->filter(fn ($m) => in_array($m->organization->status, ['pending', 'suspended'], true));

        return view('start', ['active' => $active, 'pending' => $pending]);
    }
}
