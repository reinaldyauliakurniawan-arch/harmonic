<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Fundamental;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\Seat;
use App\Services\Access;
use App\Services\HomeBlocks;

class HomeController extends Controller
{
    public function index(Organization $organization, Access $access)
    {
        $this->authorize('view', $organization);
        $checklist = null;
        if ($access->orgAdmin() || $access->leadership()) {
            $checklist = [
                ['Add seats to Winning Formation', Seat::count() > 1, 'org.formation.index'],
                ['Invite people', Membership::count() > 1 || Invitation::exists(), 'org.people.index'],
                ['Set the quarter start date', (bool) $organization->quarter_start_date, 'org.settings.edit'],
                ['Write the Fundamentals', Fundamental::where('status', 'published')->exists(), 'org.fundamentals.edit'],
                ['Add metrics', Metric::exists(), 'org.metrics.index'],
                ['Set a meeting cadence', Seat::whereNotNull('meeting_weekday')->exists(), 'org.teams.index'],
            ];
            if (collect($checklist)->every(fn ($i) => $i[1])) {
                $checklist = null;
            }
        }

        return view('org.home', ['checklist' => $checklist, 'blocks' => app(HomeBlocks::class)->build()]);
    }
}
