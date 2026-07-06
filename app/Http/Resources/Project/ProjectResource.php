<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// ══════════════════════════════════════════════════════════
/**
 * @OA\Schema(
 *   schema="ProjectResource",
 *   @OA\Property(property="id",               type="string", format="uuid"),
 *   @OA\Property(property="project_number",   type="string", example="PRJ-2024-0001"),
 *   @OA\Property(property="name",             type="string"),
 *   @OA\Property(property="description",      type="string", nullable=true),
 *   @OA\Property(property="status",           type="string", enum={"not_started","in_progress","on_hold","completed","cancelled"}),
 *   @OA\Property(property="priority",         type="string", enum={"low","medium","high","critical"}),
 *   @OA\Property(property="progress_percent", type="integer"),
 *   @OA\Property(property="start_date",       type="string", format="date", nullable=true),
 *   @OA\Property(property="end_date",         type="string", format="date", nullable=true),
 *   @OA\Property(property="budget",           type="number", nullable=true),
 *   @OA\Property(property="actual_cost",      type="number"),
 *   @OA\Property(property="is_billable",      type="boolean"),
 *   @OA\Property(property="color",            type="string", nullable=true),
 *   @OA\Property(property="tags",             type="array", @OA\Items(type="string")),
 *   @OA\Property(property="client",           type="object", nullable=true),
 *   @OA\Property(property="manager",          type="object"),
 *   @OA\Property(property="tasks_count",      type="integer"),
 *   @OA\Property(property="done_tasks_count", type="integer")
 * )
 */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'project_number'   => $this->project_number,
            'name'             => $this->name,
            'description'      => $this->description,
            'status'           => $this->status,
            'priority'         => $this->priority,
            'progress_percent' => $this->progress_percent,
            'color'            => $this->color,
            'tags'             => $this->tags ?? [],
            'is_billable'      => $this->is_billable,
            'start_date'       => $this->start_date?->toDateString(),
            'end_date'         => $this->end_date?->toDateString(),
            'actual_start_date'=> $this->actual_start_date?->toDateString(),
            'actual_end_date'  => $this->actual_end_date?->toDateString(),
            'budget'           => $this->budget ? (float) $this->budget : null,
            'actual_cost'      => (float) $this->actual_cost,
            'budget_used_pct'  => $this->budget
                ? round($this->actual_cost / $this->budget * 100, 1)
                : null,
            'is_budget_warning'=> $this->isBudgetWarning(),
            'client'           => $this->whenLoaded('client', fn() =>
                $this->client ? [
                    'id'   => $this->client->id,
                    'name' => $this->client->name,
                ] : null
            ),
            'manager'          => $this->whenLoaded('manager', fn() => [
                'id'        => $this->manager->id,
                'full_name' => $this->manager->full_name,
                'avatar_url'=> $this->manager->avatar_url,
            ]),
            'members'          => $this->whenLoaded('members', fn() =>
                $this->members->map(fn($m) => [
                    'user_id'   => $m->user_id,
                    'full_name' => $m->user?->full_name,
                    'avatar_url'=> $m->user?->avatar_url,
                    'role'      => $m->role,
                ])
            ),
            'stages'           => $this->whenLoaded('stages', fn() =>
                $this->stages->map(fn($s) => [
                    'id'           => $s->id,
                    'name'         => $s->name,
                    'color'        => $s->color,
                    'sequence'     => $s->sequence,
                    'is_done_stage'=> $s->is_done_stage,
                    'wip_limit'    => $s->wip_limit,
                ])
            ),
            'quotation_id'     => $this->quotation_id,
            'tasks_count'      => $this->tasks_count ?? null,
            'done_tasks_count' => $this->done_tasks_count ?? null,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}

// ══════════════════════════════════════════════════════════
/**
 * @OA\Schema(
 *   schema="TaskResource",
 *   @OA\Property(property="id",               type="string", format="uuid"),
 *   @OA\Property(property="project_id",       type="string", format="uuid"),
 *   @OA\Property(property="stage_id",         type="string", format="uuid"),
 *   @OA\Property(property="parent_task_id",   type="string", format="uuid", nullable=true),
 *   @OA\Property(property="title",            type="string"),
 *   @OA\Property(property="description",      type="string", nullable=true),
 *   @OA\Property(property="priority",         type="string", enum={"low","medium","high","critical"}),
 *   @OA\Property(property="start_date",       type="string", format="date", nullable=true),
 *   @OA\Property(property="due_date",         type="string", format="date", nullable=true),
 *   @OA\Property(property="estimated_hours",  type="number", nullable=true),
 *   @OA\Property(property="actual_hours",     type="number"),
 *   @OA\Property(property="progress_percent", type="integer"),
 *   @OA\Property(property="is_milestone",     type="boolean"),
 *   @OA\Property(property="is_done",          type="boolean"),
 *   @OA\Property(property="is_overdue",       type="boolean"),
 *   @OA\Property(property="tags",             type="array", @OA\Items(type="string")),
 *   @OA\Property(property="assignees",        type="array", @OA\Items(type="object")),
 *   @OA\Property(property="checklist_progress", type="integer"),
 *   @OA\Property(property="sub_tasks_count",  type="integer")
 * )
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'project_id'       => $this->project_id,
            'stage_id'         => $this->stage_id,
            'parent_task_id'   => $this->parent_task_id,
            'title'            => $this->title,
            'description'      => $this->description,
            'priority'         => $this->priority,
            'start_date'       => $this->start_date?->toDateString(),
            'due_date'         => $this->due_date?->toDateString(),
            'done_at'          => $this->done_at?->toIso8601String(),
            'estimated_hours'  => $this->estimated_hours ? (float) $this->estimated_hours : null,
            'actual_hours'     => (float) $this->actual_hours,
            'progress_percent' => $this->progress_percent,
            'is_milestone'     => $this->is_milestone,
            'is_done'          => $this->is_done,
            'is_overdue'       => $this->isOverdue(),
            'tags'             => $this->tags ?? [],
            'sequence'         => $this->sequence,

            // Stage info
            'stage'            => $this->whenLoaded('stage', fn() => [
                'id'           => $this->stage->id,
                'name'         => $this->stage->name,
                'color'        => $this->stage->color,
                'is_done_stage'=> $this->stage->is_done_stage,
            ]),

            // Assignees
            'assignees'        => $this->whenLoaded('assigneeUsers', fn() =>
                $this->assigneeUsers->map(fn($u) => [
                    'id'         => $u->id,
                    'full_name'  => $u->full_name,
                    'avatar_url' => $u->avatar_url,
                    'initials'   => collect(explode(' ', $u->full_name))
                        ->map(fn($w) => strtoupper($w[0]))->take(2)->join(''),
                ])
            ),

            // Checklists
            'checklists'       => $this->whenLoaded('checklists', fn() =>
                $this->checklists->map(fn($c) => [
                    'id'        => $c->id,
                    'title'     => $c->title,
                    'is_done'   => $c->is_done,
                    'done_at'   => $c->done_at?->toIso8601String(),
                    'sequence'  => $c->sequence,
                ])
            ),
            'checklist_progress' => $this->when(
                $this->relationLoaded('checklists'),
                fn() => $this->checklist_progress
            ),

            // Sub-tasks summary
            'sub_tasks'        => $this->whenLoaded('subTasks', fn() =>
                TaskResource::collection($this->subTasks)
            ),
            'sub_tasks_count'  => $this->whenLoaded('subTasks', fn() =>
                $this->subTasks->count()
            ),

            // Attachments
            'attachments'      => $this->whenLoaded('attachments', fn() =>
                $this->attachments->map(fn($a) => [
                    'id'            => $a->id,
                    'filename'      => $a->filename,
                    'file_url'      => $a->file_url,
                    'mime_type'     => $a->mime_type,
                    'file_size_human'=> $a->file_size_human,
                ])
            ),

            // Time logs summary
            'time_logs'        => $this->whenLoaded('timeLogs', fn() =>
                $this->timeLogs->map(fn($l) => [
                    'id'          => $l->id,
                    'user'        => $l->user?->full_name,
                    'log_date'    => $l->log_date?->toDateString(),
                    'hours'       => (float) $l->hours,
                    'description' => $l->description,
                    'is_billable' => $l->is_billable,
                ])
            ),

            // Dependencies
            'depends_on'       => $this->whenLoaded('dependsOn', fn() =>
                $this->dependsOn->map(fn($t) => [
                    'task_id'         => $t->id,
                    'title'           => $t->title,
                    'is_done'         => $t->is_done,
                    'dependency_type' => $t->pivot->dependency_type,
                ])
            ),

            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
