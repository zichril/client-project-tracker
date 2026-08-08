<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->projects();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                  ->orWhere('project_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        $allowed = ['client_name', 'project_name', 'status', 'priority', 'due_date', 'start_date', 'created_at'];
        $sortBy  = in_array($request->query('sort_by'), $allowed) ? $request->query('sort_by') : 'created_at';
        $sortDir = $request->query('sort_dir') === 'asc' ? 'asc' : 'desc';

        return ProjectResource::collection($query->orderBy($sortBy, $sortDir)->get());
    }

    public function show(Request $request, int $id)
    {
        return new ProjectResource($request->user()->projects()->findOrFail($id));
    }

    public function store(StoreProjectRequest $request)
    {
        $project = $request->user()->projects()->create($request->validated());
        return (new ProjectResource($project))->response()->setStatusCode(201);
    }

    public function update(UpdateProjectRequest $request, int $id)
    {
        $project = $request->user()->projects()->findOrFail($id);
        $project->update($request->validated());
        return new ProjectResource($project);
    }

    public function destroy(Request $request, int $id)
    {
        $request->user()->projects()->findOrFail($id)->delete();
        return response()->json(['message' => 'Project deleted successfully']);
    }
}
