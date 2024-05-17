<?php

namespace App\Http\Requests\AssetTransfer;

use App\Models\AssetMovementContainer\AssetTransferContainer;
use App\Models\AssetTransferRequest;
use App\Rules\FileOrX;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetTransferRequestRequest extends FormRequest
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
            'fixed_asset_id' => [ 'required', 'integer', 'exists:fixed_assets,id', function($attribute, $value, $fail) {
                //check if the fixed asset is already in asset transfer container
//                $inContainer = AssetTransferRequest::where('fixed_asset_id', $value)->first();
//                if($inContainer) $fail('The selected fixed asset is already in the asset transfer container.');
                $inTransferRequest = AssetTransferRequest::where('fixed_asset_id', $value)
                    ->where('status', '!==', 'Approved')->first();
                if($inTransferRequest) $fail('The selected fixed asset is already in the asset transfer request.');
            }],
            'accountable' => 'required|string',
            'company_id' => 'required|exists:companies,id',
            'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, true,$this->route('id'))],
            'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
            'remarks' => 'nullable|string',
            'attachments' => ['nullable', 'max:10000', new FileOrX],
        ];
    }
}
