<?php

namespace App\Http\Requests\MinorCategory;

use App\Rules\UniqueMajorMinorAccTitle;
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
                'account_title_sync_id'=>'required|exists:account_titles,sync_id,is_active,1',
                'major_category_id' => ['required','exists:major_categories,id,deleted_at,NULL'],
                //if minor category name and major category id has duplicate
                'minor_category_name' => ['required',new UniqueMajorMinorAccTitle()],
            ];
        }

        if ($this->isMethod('put') &&  ($this->route()->parameter('minor_category'))) {
            $id = $this->route()->parameter('minor_category');
            return [
//                'major_category_id' => 'required|exists:major_categories,id,deleted_at,NULL',
            //based on the id of the minor category, if the minor category name and major category id has duplicate
                    'account_title_sync_id'=>'required|exists:account_titles,sync_id,is_active,1',
                    'minor_category_name' => ['required',new UniqueMajorMinorAccTitle($id)],
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
            'account_title_sync_id.required'=>'Account Title is required',
            'account_title_sync_id.exists'=>'Account Title does not exist',
            'major_category_id.required' => 'Major Category is required',
            'major_category_id.exists' => 'Major Category does not exist',
            'minor_category_name.required' => 'Minor Category Name is required',
            'minor_category_name.unique' => 'Minor Category Name already been taken',
            'minor_category_name.exists' => 'Minor Category does not exist',
            'minor_category_id.exists' => 'Minor Category does not exist',
            'status.required' => 'Status is required',
            'status.boolean' => 'Status must be boolean',
        ];
    }
}
