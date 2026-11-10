<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $memberships = $user->memberships()->where('status', 'active')->with('organization')->get();
        $lastAdminSomewhere = $memberships->contains(fn ($m) => $m->is_org_admin && $m->organization->status !== 'deleted'
            && Membership::withoutGlobalScope('organization')->where('organization_id', $m->organization_id)->where('status', 'active')->where('is_org_admin', true)->count() === 1);

        return view('profile.edit', compact('user', 'memberships', 'lastAdminSomewhere'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => 'required_unless:email,'.$user->email.'|nullable|current_password',
        ], ['current_password.required_unless' => 'Enter your current password to change your email.']);
        $user->name = $data['name'];
        // shortcut: email changes take effect at once because v1 sends no email to verify the new address; add verification if email is ever enabled.
        $user->email = strtolower($data['email']);
        $user->save();

        return back()->with('status', 'Saved');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', PasswordRule::defaults()]]);
        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();
        // AuthenticateSession signs out every other session once the hash changes; keep this one.
        $request->session()->put('password_hash_web', $request->user()->getAuthPassword());

        return back()->with('status', 'Password changed');
    }

    public function theme(Request $request)
    {
        $theme = $request->validate(['theme' => 'required|in:system,light,dark'])['theme'];

        return back()->withCookie(cookie()->forever('theme', $theme))->with('status', 'Saved');
    }
}
