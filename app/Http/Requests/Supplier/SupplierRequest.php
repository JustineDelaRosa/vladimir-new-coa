<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
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
         $this->merge(['supplier_id' => $this->route()->parameter('supplier')]);
         $this->merge(['id' => $this->route()->parameter('id')]);
         
     }

    public function rules()
    {
       
        if($this->isMethod('post')){
            
            return [        
                'supplier_name' => 'required|unique:suppliers,supplier_name',
                'contact_no' => 'required|regex:[09]|numeric|digits:11',
                'address' => 'required'
            ];
            }
    
            if($this->isMethod('put') &&  ($this->route()->parameter('supplier'))){
                $id = $this->route()->parameter('supplier');
               return [
                // 'service_provider_id' => 'exists:service_providers,id,deleted_at,NULL',
                'supplier_name' => ['required',Rule::unique('suppliers','supplier_name')->ignore($id)],
                'contact_no' => 'required|regex:[09]|numeric|digits:11',
                'address' => 'required'
                   
                ];
            }
    
            if($this->isMethod('get') && ($this->route()->parameter('service_provider'))){
                return [
                    // 'service_provider_id' => 'exists:service_providers,id,deleted_at,NULL'
                ];
            }
    
            if($this->isMethod('put') && ($this->route()->parameter('id'))){
                return [
                    // 'id' => 'exists:service_providers,id',
                    'status' => 'required|boolean'
                ];
            }
    
    }
}
