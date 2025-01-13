<?php

namespace App\Http\Requests\AssetPullOut;

use Illuminate\Foundation\Http\FormRequest;

class EvaluationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'pullout_ids' => 'required|array',
            'pullout_ids.*' => ['required', 'exists:pullouts,id'],
            'evaluation' => ['nullable', 'string'],
            'attachments' => 'required|array',
            'attachments.*.attachment' => 'required|array',
            'attachments.*.attachment.*' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg', 'max:5120'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    function messages()
    {
        return [
            'pullout_ids.required' => 'Pullout ID is required',
            'pullout_ids.array' => 'Pullout ID must be an array',
            'evaluation.string' => 'Evaluation must be a string',
            'attachments.file' => 'Attachments must be a file',
            'attachments.mimes' => 'Attachments must be a pdf, doc, docx, xls, xlsx, png, jpg, jpeg',
            'attachments.max' => 'Attachments must not exceed 5MB',
            'remarks.string' => 'Remarks must be a string',
        ];
    }
}
