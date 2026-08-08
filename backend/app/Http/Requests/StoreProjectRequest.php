<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_name'  => 'required|string|max:255',
            'project_name' => 'required|string|max:255',
            'description'  => 'nullable|string',
            'status'       => 'required|in:Planning,In Progress,On Hold,Completed',
            'priority'     => 'required|in:Low,Medium,High',
            'start_date'   => 'nullable|date',
            'due_date'     => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
