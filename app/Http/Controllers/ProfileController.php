<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'avatar' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,webp',
                'dimensions:max_width=1024,max_height=1024',
                'max:512',
            ],
        ]);

        $user = $request->user();

        if ($request->has('avatar')) {
            if ($user->avatar) {
                Storage::disk(User::avatarDisk($user->avatar))->delete($user->avatar);
            }

            $data['avatar'] = $request->hasFile('avatar')
                ? $request->file('avatar')->store('', 'avatars')
                : null;
        }

        $user->update($data);

        return response()->json($user->fresh());
    }
}
