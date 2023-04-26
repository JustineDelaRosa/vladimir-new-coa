<?php

namespace App\Http\Requests\MajorCategory;

use App\Models\Division;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class MajorCategoryRequest extends FormRequest
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
        $this->merge(['major_category_id' => $this->route()->parameter('major_category')]);
        $this->merge(['id' => $this->route()->parameter('id')]);
    }


    public function rules()
    {


        if ($this->isMethod('post')) {


            //check if division_name is trash
            return [
                'division_name' => 'required|exists:divisions,division_name,deleted_at,NULL',
                //unique validation for major_category_name in division_id and if trash
                'major_category_name' => ['required', Rule::unique('major_categories', 'major_category_name')->where(function ($query) {
                })],



                // 'major_category_name' => 'required|unique:major_categories,major_category_name',
                // 'classification' => 'required|in:small_tools,machinery_&_equipment,mobile_phone,vechicle'
            ];
        }

        if ($this->isMethod('put') &&  ($this->route()->parameter('major_category'))) {
            $id = $this->route()->parameter('major_category');
            return [
                // 'major_category_id' => 'exists:major_categories,id,deleted_at,NULL',
                'major_category_name' => ['required', Rule::unique('major_categories', 'major_category_name')->ignore($id)],
                // 'classification' => 'required|in:small_tools,machinery_&_equipment,mobile_phone,vechicle'

            ];
        }

        if ($this->isMethod('get') && ($this->route()->parameter('major_category'))) {
            return [
                // 'major_category_id' => 'exists:major_categories,id,deleted_at,NULL'
            ];
        }

        if ($this->isMethod('put') && ($this->route()->parameter('id'))) {
            return [
                'status' => 'required|boolean',
                // 'id' => 'exists:major_categories,id',
            ];
        }
    }

    function messages()
    {
        return [
            'division_name.required' => 'Division name is required',
            'division_name.exists' => 'Division name does not exist',
            'major_category_name.required' => 'Major category name is required',
            'major_category_name.unique' => 'Major category name already exist',
            'status.required' => 'Status is required',
            'status.boolean' => 'Status must be boolean',
            'id.exists' => 'Major category id does not exist',

        ];
    }
}
