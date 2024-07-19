<?php

namespace App\Http\Requests\AssetRelease;

use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountabilityRequest extends FormRequest
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
        return [
            'warehouse_number_id' => [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    $departmentIds = [];

                    foreach ($value as $warehouseId) {
                        $fixedAssetDepartmentId = FixedAsset::where('warehouse_number_id', $warehouseId)->value('department_id');
                        if ($fixedAssetDepartmentId !== null) {
                            $departmentIds[] = $fixedAssetDepartmentId;
                        }

                        $additionalCostDepartmentId = AdditionalCost::where('warehouse_number_id', $warehouseId)->value('department_id');
                        if ($additionalCostDepartmentId !== null) {
                            $departmentIds[] = $additionalCostDepartmentId;
                        }
                    }

                    if (count(array_unique($departmentIds)) > 1) {
                        $fail('The department of the selected items is not the same.');
                    }
                }
            ],
            'accountability' => ['required', 'string'],
            'accountable' => ['required-if:accountability,Personal Issued', 'string'],
        ];
    }
}
