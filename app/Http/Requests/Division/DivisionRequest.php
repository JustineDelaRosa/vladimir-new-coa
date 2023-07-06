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
                "division_name" => ["unique:divisions,division_name", "required"]
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('division'))) {
            $id = $this->route()->parameter('division');
            return [
                "division_name" => ["required", Rule::unique('divisions', 'division_name')->ignore($id)]
            ];
        }
    }
}
