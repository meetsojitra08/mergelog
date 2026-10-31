<?php

namespace App\Http\Controllers;

use App\Enums\ChangeEntryStatus;
use App\Jobs\SummarizePullRequest;
use App\Models\ChangeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ChangeEntryController extends Controller
{
    public function retry(Request $request, string $changeEntry): JsonResponse
    {
        $entry = ChangeEntry::query()
            ->whereHas('pullRequest.repository', fn (Builder $query): Builder => $query->where('user_id', $request->user()->id))
            ->findOrFail($changeEntry);

        $claimed = ChangeEntry::query()
            ->whereKey($entry->id)
            ->where('status', ChangeEntryStatus::Failed)
            ->update([
                'status' => ChangeEntryStatus::PendingAi,
                'error_message' => null,
            ]);

        if ($claimed !== 1) {
            return response()->json(['message' => 'Only failed entries can be retried.'], 422);
        }

        try {
            SummarizePullRequest::dispatch($entry->pullRequest);
        } catch (Throwable $exception) {
            ChangeEntry::query()->whereKey($entry->id)
                ->where('status', ChangeEntryStatus::PendingAi)
                ->update([
                    'status' => ChangeEntryStatus::Failed,
                    'error_message' => $exception->getMessage(),
                ]);

            throw $exception;
        }

        return response()->json(['message' => 'Summary retry queued.'], 202);
    }
}
