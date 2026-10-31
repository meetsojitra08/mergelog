<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepositoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->routeIs('repositories.index')) {
            return [
                'id' => $this->id,
                'full_name' => $this->full_name,
                'pending_count' => $this->pending_count,
                'drafted_count' => $this->drafted_count,
                'created_at' => $this->created_at,
            ];
        }

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'webhook_url' => rtrim((string) config('app.url'), '/').'/api/webhooks/github/'.$this->id,
            'webhook_secret' => $this->webhook_secret,
        ];
    }
}
