<?php

namespace App\Http\Controllers;

use App\Enums\ChangeEntryStatus;
use App\Jobs\SummarizePullRequest;
use App\Models\ChangeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangeEntryController extends Controller
{
    public function retry(Request $request, string $changeEntry): JsonResponse
    {
        $entry = ChangeEntry::query()
            ->whereHas('pullRequest.repository', fn (Builder $query): Builder => $query->where('user_id', $request->user()->id))
            ->findOrFail($changeEntry);

        if ($entry->status !== ChangeEntryStatus::Failed) {
            return response()->json(['message' => 'Only failed entries can be retried.'], 422);
        }

        $entry->fill([
            'status' => ChangeEntryStatus::PendingAi,
            'error_message' => null,
        ])->save();

        SummarizePullRequest::dispatch($entry->pullRequest);

        return response()->json(['message' => 'Summary retry queued.'], 202);
    }
}
