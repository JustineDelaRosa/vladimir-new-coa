<?php

namespace App\Http\Requests\CategoryList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryListRequest extends FormRequest
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
        $this->merge(['id' => $this->route()->parameter('id')]);
        $this->merge(['categorylist' => $this->request->get('service_provider_id')]);
    }
    public function rules()
    {
        if ($this->isMethod('post')) {

            return [
                // 'categorylist' => 'unique:category_lists,service_provider_id,'.request('service_provider_id').',id,major_category_id,'.request('major_category_id'),
                'service_provider_id' => 'required|exists:service_providers,id',
                'major_category_id' => 'required|exists:major_categories,id'

            ];
        }

        if ($this->isMethod('put') &&  ($this->route()->parameter('category_list'))) {
            $id = $this->route()->parameter('category_list');
            return [
                'categorylist' => Rule::unique('category_lists', 'service_provider_id')
                    ->where('major_category_id', request('major_category_id'))
                    ->ignore($id),
                'service_provider_id' => 'required|exists:service_providers,id',
                'major_category_id' => 'required|exists:major_categories,id'

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

        // if($this->isMethod('put') && ($this->route()->parameter('category_id'))){
        //     return [
        //         ''
        //     ]
        // }
    }
}
