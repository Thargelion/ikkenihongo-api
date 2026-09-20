<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => ['sometimes', 'required', 'string', 'max:255'],
            'birthdate' => ['sometimes', 'nullable', 'date', 'before:today'],
            'avatar' => ['sometimes', 'nullable', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($request->has('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $data['avatar'] = $request->hasFile('avatar')
                ? $request->file('avatar')->store('avatars', 'public')
                : null;
        }

        $user->update($data);

        return response()->json($user->fresh());
    }
}
