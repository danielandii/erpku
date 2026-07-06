<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'content'           => $this->content,
            'parent_comment_id' => $this->parent_comment_id,
            'mentions'          => $this->mentions ?? [],
            'is_edited'         => $this->isEdited(),
            'edited_at'         => $this->edited_at?->toIso8601String(),
            'user'              => $this->whenLoaded('user', fn() => [
                'id'         => $this->user->id,
                'full_name'  => $this->user->full_name,
                'avatar_url' => $this->user->avatar_url,
                'initials'   => collect(explode(' ', $this->user->full_name))
                    ->map(fn($w) => strtoupper($w[0]))->take(2)->join(''),
            ]),
            'replies'           => $this->whenLoaded('replies', fn() =>
                TaskCommentResource::collection($this->replies)
            ),
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}

class TaskTimeLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'log_date'    => $this->log_date?->toDateString(),
            'hours'       => (float) $this->hours,
            'description' => $this->description,
            'is_billable' => $this->is_billable,
            'user'        => $this->whenLoaded('user', fn() => [
                'id'        => $this->user->id,
                'full_name' => $this->user->full_name,
            ]),
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
