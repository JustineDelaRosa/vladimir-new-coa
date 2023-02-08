<?php

namespace App\Http\Requests\MajorCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
       
        
        if($this->isMethod('post')){
        
            return [
                'major_category_name' => 'required|unique:major_categories,major_category_name'
            ];
            }
    
            if($this->isMethod('put') &&  ($this->route()->parameter('major_category'))){
                $id = $this->route()->parameter('major_category');
                return [
                // 'major_category_id' => 'exists:major_categories,id,deleted_at,NULL',
                'major_category_name' => ['required',Rule::unique('major_categories','major_category_name')->ignore($id)]
                    
                ];
            }

            if($this->isMethod('get') && ($this->route()->parameter('major_category'))){
                return [
                    // 'major_category_id' => 'exists:major_categories,id,deleted_at,NULL'
                ];
            }
    
            if($this->isMethod('put') && ($this->route()->parameter('id'))){
                return [
                    'status' => 'required|boolean',
                    // 'id' => 'exists:major_categories,id',
                ];
            }

        
    }
}
