<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestController extends Controller
{
    public function __invoke(): Response
    {
        $user = User::create([
            'name' => 'Invitado',
            'nickname' => 'Invitado',
            'email' => 'guest-'.Str::uuid().'@guest.invalid',
            'password' => Hash::make(Str::random(40)),
            'is_guest' => true,
        ]);
        Auth::login($user);

        return response()->noContent();
    }
}
