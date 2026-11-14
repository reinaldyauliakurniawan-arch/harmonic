<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\RubricLevel;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Onboarding
{
    public const RUBRIC = [1 => 'Not yet', 2 => 'Developing', 3 => 'Solid', 4 => 'Strong', 5 => 'Role model'];

    public const RUBRIC_TEXT = [
        1 => 'Rarely shows this yet; needs clear examples and close support.',
        2 => 'Shows it sometimes, mostly when reminded or when the stakes are low.',
        3 => 'Shows it reliably in day-to-day work without being asked.',
        4 => 'Shows it even under pressure, and others notice the difference it makes.',
        5 => 'Shows it every time and helps others do the same; people point to them as the example.',
    ];

    public function __construct(private Tenant $tenant) {}

    /** New organization with its root seat and seeded rubric levels, run inside the org's tenant context. */
    public function createOrganization(string $name, string $status, string $source, ?User $founder = null): array
    {
        return DB::transaction(function () use ($name, $status, $source, $founder) {
            $org = new Organization(['name' => $name]);
            $org->slug = Organization::uniqueSlug($name);
            $org->status = $status;
            $org->signup_source = $source;
            $org->approved_at = $status === 'active' ? now() : null;
            $org->save();
            $org->refresh();

            $previous = [$this->tenant->organization, $this->tenant->membership];
            $this->tenant->set($org, null);

            $membership = null;
            if ($founder) {
                $membership = new Membership;
                $membership->user_id = $founder->id;
                $membership->status = 'active';
                $membership->is_org_admin = true;
                $membership->joined_at = now();
                $membership->save();
            }
            $root = new Seat(['title' => 'Chief Executive', 'responsibilities' => []]);
            $root->holder_id = $membership?->id;
            $root->is_leadership_team = true;
            $root->save();
            foreach (self::RUBRIC as $score => $label) {
                RubricLevel::create(['score' => $score, 'label' => $label, 'description' => self::RUBRIC_TEXT[$score]]);
            }
            self::seedLeadership($org->id);
            if ($membership) {
                $this->tenant->membership = $membership;
                ActivityLog::record('member.joined', $membership, ['founder' => true]);
            }

            $previous[0] ? $this->tenant->set(...$previous) : $this->tenant->forget();

            return [$org, $membership, $root];
        });
    }

    /** Copies the leadership roles and items from config/assessment.php into one organization; also used by a migration, so it writes rows directly. */
    public static function seedLeadership(int $orgId): void
    {
        foreach (config('assessment.leadership') as $t => $type) {
            $typeId = DB::table('assessment_types')->insertGetId(['organization_id' => $orgId, 'name' => $type['name'], 'sort_order' => $t + 1]);
            DB::table('assessment_items')->insert(array_map(fn ($item, $i) => [
                'type_id' => $typeId, 'text' => $item[0], 'levels' => json_encode($item[1]), 'sort_order' => $i + 1,
            ], $type['items'], array_keys($type['items'])));
        }
    }

    /** Creates an invitation and returns the plain token, which is shown once and never stored. */
    public function invite(Organization $org, string $email, bool $admin, ?int $seatId, ?int $by): array
    {
        $token = Str::random(43);
        $inv = new Invitation(['email' => strtolower($email), 'make_org_admin' => $admin, 'seat_id' => $seatId]);
        $inv->organization_id = $org->id;
        $inv->token_hash = hash('sha256', $token);
        $inv->invited_by = $by;
        $inv->expires_at = now()->addDays(7);
        $inv->save();

        return [$inv, $token];
    }

    public function reissue(Invitation $inv): string
    {
        $token = Str::random(43);
        $inv->token_hash = hash('sha256', $token);
        $inv->expires_at = now()->addDays(7);
        $inv->revoked_at = null;
        $inv->save();

        return $token;
    }

    /** Accepts an invitation for a user. Returns [membership, seatSkipped]. */
    public function accept(Invitation $inv, User $user): array
    {
        return DB::transaction(function () use ($inv, $user) {
            $org = Organization::findOrFail($inv->organization_id);
            $this->tenant->set($org, null);
            $m = Membership::where('user_id', $user->id)->first();
            $rejoin = $m && $m->status === 'departed';
            if (! $m) {
                $m = new Membership;
                $m->user_id = $user->id;
            }
            $m->status = 'active';
            $m->joined_at = $m->joined_at && ! $rejoin ? $m->joined_at : now();
            $m->departed_at = null;
            $m->is_org_admin = $m->is_org_admin || $inv->make_org_admin;
            $m->save();
            $this->tenant->membership = $m;

            $skipped = false;
            if ($inv->seat_id) {
                $seat = Seat::lockForUpdate()->find($inv->seat_id);
                if ($seat && ! $seat->holder_id) {
                    $seat->holder_id = $m->id;
                    $seat->save();
                    ActivityLog::record('seat.assigned', $seat, ['member' => $m->id]);
                } else {
                    $skipped = true;
                }
            }
            $inv->accepted_at = now();
            $inv->save();
            ActivityLog::record($rejoin ? 'member.rejoined' : 'member.joined', $m, ['seat_not_assigned' => $skipped]);

            return [$m, $skipped];
        });
    }
}
