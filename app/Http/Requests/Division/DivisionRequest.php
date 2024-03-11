<?php

namespace App\Http\Requests\Division;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DivisionRequest extends FormRequest
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
        if ($this->isMethod('post')) {
            return [
                "division_name" => ["unique:divisions,division_name", "required"],
                "sync_id" => ["required", Rule::exists('departments', 'sync_id')->whereNull('division_id')]
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('division'))) {
            $id = $this->route()->parameter('division');
            return [
                "division_name" => ["required", Rule::unique('divisions', 'division_name')->ignore($id)],
                //check if department division_id is not null
                //if not null, check if department division_id is equal to division id
                //if equal, return true else return false
                "sync_id" => ["required", Rule::exists('departments', 'sync_id')->where(function ($query) use ($id) {
                    $query->whereNull('division_id')->orWhere('division_id', $id);
                })]
            ];
        }
    }

    function messages()
    {
        return [
            'sync_id.exists' => 'The department already have a division.',
            'sync_id.required' => 'The department is required.'
        ];
    }
}
