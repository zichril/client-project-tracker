<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'clientName'  => $this->client_name,
            'projectName' => $this->project_name,
            'description' => $this->description,
            'status'      => $this->status,
            'priority'    => $this->priority,
            'startDate'   => $this->start_date?->format('Y-m-d'),
            'dueDate'     => $this->due_date?->format('Y-m-d'),
            'createdAt'   => $this->created_at->toISOString(),
        ];
    }
}
