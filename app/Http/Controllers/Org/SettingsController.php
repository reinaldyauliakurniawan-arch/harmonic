<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Organization;
use DateTimeZone;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Organization $organization)
    {
        $this->authorize('manage', $organization);

        return view('org.settings', ['org' => $organization, 'zones' => DateTimeZone::listIdentifiers()]);
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorize('manage', $organization);
        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'timezone' => 'required|timezone:all',
            'week_start_day' => 'required|integer|between:1,7',
            'quarter_start_date' => 'required|date',
            'evaluator_bar_max_mixed' => 'required|integer|between:0,20',
            'evaluator_bar_max_minus' => 'required|integer|between:0,20',
        ]);
        $old = $organization->quarter_start_date?->toDateString();
        if ($old && $old !== $data['quarter_start_date'] && ! $request->boolean('confirmed')) {
            return back()->withInput()->with('confirm_quarter', true);
        }
        $organization->fill($data);
        $changed = array_keys($organization->getDirty());
        $organization->save();
        if ($changed) {
            ActivityLog::record('org.settings_changed', $organization, ['fields' => $changed]);
        }

        return redirect()->route('org.settings.edit')->with('status', 'Saved');
    }
}
