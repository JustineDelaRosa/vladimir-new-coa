<?php

namespace App\Http\Requests\MinorCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MinorCategoryRequest extends FormRequest
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


    protected function prepareForValidation()
    {
        $this->merge(['minor_category_id' => $this->route()->parameter('minor_category')]);
        $this->merge(['id' => $this->route()->parameter('id')]);
    }

    public function rules()
    {
        if ($this->isMethod('post')) {
            return [
                'major_category_id' => 'required|exists:major_categories,id,deleted_at,NULL',
                //if minor category name and major category id has duplicate
                'minor_category_name' => ['required', Rule::unique('minor_categories', 'minor_category_name')->where(function ($query) {
                    return $query->where('major_category_id', $this->major_category_id)->whereNull('deleted_at');
                })],
            ];
        }

        if ($this->isMethod('put') &&  ($this->route()->parameter('minor_category'))) {
            $id = $this->route()->parameter('minor_category');
            return [
                'major_category_id' => 'required|exists:major_categories,id,deleted_at,NULL',
                'minor_category_name' => 'required',
            ];
        }

        if ($this->isMethod('get') && ($this->route()->parameter('minor_category'))) {
            return [
                // 'minor_category_id' => 'exists:minor_categories,id,deleted_at,NULL'
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean',
                // 'id' => 'exists:minor_categories,id',
            ];
        }
    }

    function messages()
    {
        return [
            'major_category_name.required' => 'Major Category Name is required',
            'major_category_name.exists' => 'Major Category Name does not exist',
            'major_category_name.unique' => 'Major Category Name already exists',
            'minor_category_name.required' => 'Minor Category Name is required',
            'minor_category_name.unique' => 'Minor Category Name already exists for this major category',
            'status.required' => 'Status is required',
            'status.boolean' => 'Status must be boolean',
        ];
    }
}
