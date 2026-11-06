<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->load('media'),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $request->safe()->only(['name', 'email']);
        $profile['email'] = mb_strtolower($profile['email']);
        $user->update($profile);
        $photoUpdated = false;
        $photoRemoved = false;

        if ($request->hasFile('photo')) {
            $user->addMediaFromRequest('photo')->toMediaCollection('profile-photo');
            $photoUpdated = true;
        }

        if (! $photoUpdated && $request->boolean('remove_photo')) {
            $user->clearMediaCollection('profile-photo');
            $photoRemoved = true;
        }

        activity('users')
            ->causedBy($user)
            ->performedOn($user)
            ->event('profile_updated')
            ->withProperties(['photo_updated' => $photoUpdated, 'photo_removed' => $photoRemoved])
            ->log('Actualizó su perfil personal');

        return to_route('profile.edit')->with(
            'success',
            $photoUpdated
                ? 'Tu perfil y fotografía se actualizaron correctamente.'
                : ($photoRemoved
                    ? 'Tu perfil se actualizó y la fotografía fue eliminada.'
                    : 'Tu información personal se actualizó correctamente.'),
        );
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();
        activity('users')
            ->causedBy($user)
            ->performedOn($user)
            ->event('password_changed')
            ->log('Actualizó su contraseña');

        return to_route('profile.edit')->with('success', 'Tu contraseña se actualizó correctamente.');
    }
}
