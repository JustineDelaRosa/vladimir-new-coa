<?php

namespace App\Http\Requests\MajorCategory;

use App\Models\Division;
use App\Models\MajorCategory;
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

            return [
                'major_category_name' => ['required', function ($attribute, $value, $fail) {
                    $majorCategory =  MajorCategory::withTrashed()->where('major_category_name', $value)
                        ->exists();
                    if($majorCategory){
                        $fail('Major category already exist');
                    }
                }],
                'est_useful_life'=> 'required|numeric|max:100',
                // , Rule::unique('major_categories', 'major_category_name')->where(function ($query) {
                //     return $query->where('division_id', $this->division_id)->whereNull('deleted_at');
                // }),
            ];
        }

        if ($this->isMethod('put') &&  ($this->route()->parameter('major_category'))) {
            $id = $this->route()->parameter('major_category');
            return [
                'major_category_name' => ['required', function ($attribute, $value, $fail) use ($id) {
                   $majorCategory =  MajorCategory::where(['major_category_name' => $value])
                        ->where('id', '!=', $id)
                        ->withTrashed()
                        ->first();
                   if($majorCategory){
                       $fail('Major category already exist');
                   }
                }],
                'est_useful_life' => 'required|numeric|max:100',
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
            'major_category_name.required' => 'Major category name is required',
            // 'major_category_name.unique' => 'Major category already exist for this division',
            'status.required' => 'Status is required',
            'status.boolean' => 'Status must be boolean',


        ];
    }
}
