<?php

namespace App\Http\Controllers;

use App\Enums\ChangeEntryStatus;
use App\Http\Requests\StoreRepositoryRequest;
use App\Http\Resources\RepositoryResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class RepositoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $repositories = $request->user()->repositories()
            ->withCount([
                'pullRequests as pending_count' => function (Builder $query): void {
                    $query->whereHas('changeEntry', function (Builder $query): void {
                        $query->where('status', ChangeEntryStatus::PendingAi->value);
                    });
                },
                'pullRequests as drafted_count' => function (Builder $query): void {
                    $query->whereHas('changeEntry', function (Builder $query): void {
                        $query->where('status', ChangeEntryStatus::Drafted->value);
                    });
                },
            ])
            ->latest()
            ->get();

        return response()->json(RepositoryResource::collection($repositories)->resolve($request));
    }

    public function store(StoreRepositoryRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $repository = $request->user()->repositories()->create([
            'full_name' => $attributes['full_name'],
            'webhook_secret' => Str::random(40),
            'github_token' => $attributes['github_token'] ?? null,
        ]);

        return response()->json(RepositoryResource::make($repository)->resolve($request), Response::HTTP_CREATED);
    }

    public function show(Request $request, int $repository): JsonResponse
    {
        $repository = $request->user()->repositories()->findOrFail($repository);

        return response()->json(RepositoryResource::make($repository)->resolve($request));
    }

    public function destroy(Request $request, int $repository): Response
    {
        $repository = $request->user()->repositories()->findOrFail($repository);
        $repository->delete();

        return response()->noContent();
    }
}
