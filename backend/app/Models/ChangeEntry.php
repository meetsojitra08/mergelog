<?php

namespace App\Models;

use App\Enums\ChangeCategory;
use App\Enums\ChangeEntryStatus;
use Database\Factories\ChangeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pull_request_id',
    'category',
    'summary',
    'status',
    'ai_raw_response',
    'error_message',
])]
class ChangeEntry extends Model
{
    /** @use HasFactory<ChangeEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => ChangeCategory::class,
            'status' => ChangeEntryStatus::class,
            'ai_raw_response' => 'array',
        ];
    }

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }
}
