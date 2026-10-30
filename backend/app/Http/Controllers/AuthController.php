<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());
        $token = $user->createToken('mergelog')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => UserResource::make($user)->resolve($request),
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('mergelog')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(UserResource::make($request->user())->resolve($request));
    }
}
