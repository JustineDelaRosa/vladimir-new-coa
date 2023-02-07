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
        if($this->isMethod('post')){
        
            return [
                'minor_category_name' => 'required|unique:minor_categories,minor_category_name',
                'urgency_level' => 'required|in:HIGH,MEDIUM,LOW',
                'personally_assign' => 'required|boolean',
                'evaluate_in_every_movement' => 'required|boolean'
                
            ];
            }

            if($this->isMethod('put') &&  ($this->route()->parameter('minor_category'))){
                $id = $this->route()->parameter('minor_category');
                return [
                'minor_category_id' => 'exists:minor_categories,id,deleted_at,NULL',
                'minor_category_name' => ['required',Rule::unique('minor_categories','minor_category_name')->ignore($id)],
                'urgency_level' => 'required|in:HIGH,MEDIUM,LOW',
                'personally_assign' => 'required|boolean',
                'evaluate_in_every_movement' => 'required|boolean'
                    
                ];
            }

            if($this->isMethod('get') && ($this->route()->parameter('minor_category'))){
                return [
                    'minor_category_id' => 'exists:minor_categories,id,deleted_at,NULL'
                ];
            }
    
            if($this->isMethod('put') && ($this->route()->parameter('id'))){
                return [
                    'status' => 'required|boolean',
                    'id' => 'exists:minor_categories,id',
                ];
            }
    
    }
}
