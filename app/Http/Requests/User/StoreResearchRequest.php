<?php

namespace App\Http\Requests\User;

use App\Models\ResearchRequest;
use App\Models\ResearchRequestFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The research work request form (website and MHub app). */
class StoreResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'research_type' => ['required', Rule::in(array_keys(ResearchRequest::TYPES))],
            'field_of_study' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:30', 'max:20000'],
            'academic_level' => ['required', Rule::in(array_keys(ResearchRequest::LEVELS))],
            'purpose' => ['nullable', 'string', 'max:200'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today', 'before:'.now()->addYears(3)->toDateString()],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['nullable', Rule::in(ResearchRequest::CURRENCIES)],
            'pages' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'citation_style' => ['nullable', Rule::in(array_keys(ResearchRequest::CITATION_STYLES))],
            'priority' => ['nullable', Rule::in(array_keys(ResearchRequest::PRIORITIES))],
            'files' => ['nullable', 'array', 'max:'.ResearchRequestFile::MAX_FILES],
            'files.*' => ResearchRequestFile::rule(),
        ];
    }

    public function messages(): array
    {
        return [
            'description.min' => 'Describe the work in a bit more detail (at least 30 characters): topic, what you need, and anything the team must follow.',
            'deadline.after_or_equal' => 'The deadline cannot be in the past.',
            'files.*.extensions' => 'Allowed files: '.implode(', ', ResearchRequestFile::EXTENSIONS).'.',
            'files.*.max' => 'Each file can be at most '.(ResearchRequestFile::MAX_KB / 1024).' MB.',
        ];
    }
}
