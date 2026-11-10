<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Seat;
use App\Models\User;
use App\Services\Onboarding;
use App\Services\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PeopleController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        $this->authorize('manage', $organization);
        $status = in_array($request->query('status'), ['active', 'departed'], true) ? $request->query('status') : 'active';
        $members = Membership::with(['user', 'seats'])->where('status', $status)
            ->join('users', 'users.id', '=', 'memberships.user_id')->orderBy('users.name')->select('memberships.*')
            ->paginate(25)->withQueryString();
        $invitations = Invitation::whereNull('accepted_at')->whereNull('revoked_at')->with('seat')->latest()->get();
        $vacant = Seat::whereNull('holder_id')->orderBy('title')->pluck('title', 'id');
        // F-03: invited seats that were filled before the person accepted, shown to admins for two weeks.
        $seatSkipped = ActivityLog::with('actor.user')->whereIn('action', ['member.joined', 'member.rejoined'])
            ->where('data->seat_not_assigned', true)->where('created_at', '>=', now()->subDays(14))->latest('created_at')->get();

        return view('org.people', compact('members', 'invitations', 'vacant', 'status', 'seatSkipped'));
    }

    public function invite(Request $request, Organization $organization, Onboarding $onboarding)
    {
        $this->authorize('manage', $organization);
        $data = $request->validate([
            'emails' => 'required|string|max:4000',
            'seat_id' => 'nullable|integer',
            'make_org_admin' => 'boolean',
        ]);
        $emails = collect(preg_split('/[\s,;]+/', strtolower($data['emails'])))->filter()->unique()->values();
        if ($emails->count() > 20) {
            throw ValidationException::withMessages(['emails' => 'Invite at most 20 people at a time.']);
        }
        foreach ($emails as $e) {
            if (! filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($e) > 190) {
                throw ValidationException::withMessages(['emails' => "\"$e\" isn't a valid email."]);
            }
        }
        $seatId = null;
        if ($data['seat_id'] ?? null) {
            $seat = Seat::whereNull('holder_id')->find($data['seat_id']);
            if (! $seat) {
                throw ValidationException::withMessages(['seat_id' => 'Choose a vacant seat.']);
            }
            if ($emails->count() > 1) {
                throw ValidationException::withMessages(['seat_id' => 'A seat holds one person. Invite one email when choosing a seat.']);
            }
            $seatId = $seat->id;
        }
        $links = [];
        $already = [];
        $me = app(Tenant::class)->membership;
        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();
            if ($user && Membership::where('user_id', $user->id)->where('status', 'active')->exists()) {
                $already[] = $email;

                continue;
            }
            Invitation::where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            [$inv, $token] = $onboarding->invite($organization, $email, (bool) ($data['make_org_admin'] ?? false), $seatId, $me->id);
            ActivityLog::record('member.invited', $inv, ['email' => $email]);
            $links[$email] = route('invitations.show', $token);
        }

        return back()->with('links', $links)->with('already', $already)->with('status', $links ? 'Invitation links created' : 'Already a member.');
    }

    public function resend(Organization $organization, Invitation $invitation, Onboarding $onboarding)
    {
        $this->authorize('manage', $organization);
        abort_if($invitation->accepted_at, 404);
        $token = $onboarding->reissue($invitation);

        return back()->with('links', [$invitation->email => route('invitations.show', $token)])->with('status', 'New link created. The old one no longer works');
    }

    public function revoke(Organization $organization, Invitation $invitation)
    {
        $this->authorize('manage', $organization);
        $invitation->revoked_at = now();
        $invitation->save();

        return back()->with('status', 'Invitation revoked');
    }

    public function toggleAdmin(Organization $organization, Membership $membership)
    {
        $this->authorize('manage', $organization);
        abort_unless($membership->status === 'active', 404);
        if ($membership->is_org_admin && Membership::active()->where('is_org_admin', true)->count() === 1) {
            return back()->withErrors(['member' => 'Make someone else an admin first.']);
        }
        $membership->is_org_admin = ! $membership->is_org_admin;
        $membership->save();
        ActivityLog::record('member.role_changed', $membership, ['is_org_admin' => $membership->is_org_admin]);
        $membership->user->invalidateSessions();

        return back()->with('status', $membership->is_org_admin ? 'Now an Org Admin' : 'No longer an Org Admin');
    }

    public function reactivate(Organization $organization, Membership $membership)
    {
        $this->authorize('manage', $organization);
        abort_unless($membership->status === 'departed' && ! $membership->user->anonymized_at, 404);
        $membership->status = 'active';
        $membership->departed_at = null;
        $membership->joined_at = now();
        $membership->save();
        ActivityLog::record('member.rejoined', $membership);

        return back()->with('status', 'Reactivated');
    }

    public function resetLink(Organization $organization, Membership $membership)
    {
        $this->authorize('manage', $organization);
        abort_unless($membership->status === 'active', 404);
        $user = $membership->user;

        return back()->with('links', [$user->email => route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email])])
            ->with('link_kind', 'reset')->with('status', 'Reset link created');
    }
}
