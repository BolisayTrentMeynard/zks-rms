<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Profile for Guest and Admin: name, contact number, email. No history or payment info. */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9+()\s-]{7,20}$/'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ], [
            'phone.regex' => 'Enter a valid contact number, for example 09171234567.',
            'email.unique' => 'This email is already used by another account.',
        ]);

        $user->fill([
            'name' => trim($data['name']),
            'phone' => trim($data['phone']),
            'email' => $data['email'],
        ]);

        if ($user->isDirty()) {
            $user->save();
            ActivityLogger::account('profile_edited', $user, 'Profile edited');
        }

        return redirect()->route('profile.edit')->with('status', 'Profile saved.');
    }
}
