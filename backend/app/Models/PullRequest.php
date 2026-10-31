<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'repository_id',
    'github_pr_number',
    'title',
    'body',
    'author',
    'html_url',
    'merged_at',
    'github_delivery_id',
])]
class PullRequest extends Model
{
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function changeEntry(): HasOne
    {
        return $this->hasOne(ChangeEntry::class);
    }
}
