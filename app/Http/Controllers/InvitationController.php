<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Services\Onboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;

class InvitationController extends Controller
{
    private function find(string $token): ?Invitation
    {
        $inv = Invitation::withoutGlobalScope('organization')->where('token_hash', hash('sha256', $token))->first();
        if (! $inv || ! $inv->isPending()) {
            return null;
        }
        $org = Organization::find($inv->organization_id);

        return $org && $org->status === 'active' ? $inv->setRelation('org', $org) : null;
    }

    public function show(Request $request, string $token)
    {
        $inv = $this->find($token);
        if (! $inv) {
            return response()->view('invitations.invalid', [], 410);
        }
        $exists = User::where('email', $inv->email)->exists();
        session(['url.intended' => url()->current()]);

        return view('invitations.show', ['inv' => $inv, 'token' => $token, 'exists' => $exists, 'user' => $request->user()]);
    }

    public function accept(Request $request, string $token, Onboarding $onboarding)
    {
        $inv = $this->find($token);
        if (! $inv) {
            return response()->view('invitations.invalid', [], 410);
        }
        $user = $request->user();
        if (! $user) {
            abort_if(User::where('email', $inv->email)->exists(), 403);
            $data = $request->validate(['name' => 'required|string|min:2|max:120', 'password' => ['required', 'confirmed', PasswordRule::defaults()]]);
            $user = User::create(['name' => $data['name'], 'email' => $inv->email, 'password' => $data['password']]);
            $user->forceFill(['email_verified_at' => now()])->save();
            Auth::login($user);
            $request->session()->regenerate();
        }
        abort_unless(strtolower($user->email) === $inv->email, 403);
        [, $skipped] = $onboarding->accept($inv, $user);

        return redirect()->route('org.home', ['organization' => $inv->org->slug])
            ->with('status', $skipped ? "You've joined. Seat was already filled, so it wasn't assigned." : "You've joined {$inv->org->name}.");
    }
}
