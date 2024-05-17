<?php

namespace App\Http\Requests\AssetMovement\AssetTransfer;

use App\Models\AssetMovementContainer\AssetTransferContainer;
use App\Models\AssetTransferRequest;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Illuminate\Foundation\Http\FormRequest;

class CreateAssetTransferContainerRequest extends FormRequest
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
                $inContainer = AssetTransferContainer::where('fixed_asset_id', $value)->first();
                if($inContainer) $fail('The selected fixed asset is already in the asset transfer container.');
                $inTransferRequest = AssetTransferRequest::where('fixed_asset_id', $value)
                    ->where('status', '!==', 'Approved')->first();
                if($inTransferRequest) $fail('The selected fixed asset is already in the asset transfer request.');
            }],
            'accountable' => 'required|string',
            'company_id' => 'required|exists:companies,id',
            'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, true)],
            'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
            'remarks' => 'nullable|string',
            'description' => 'required',
            'attachments.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ];
    }

    function messages()
    {
        return [
            'fixed_asset_id.required' => 'The fixed asset field is required.',
            'fixed_asset_id.integer' => 'The fixed asset field must be an integer.',
            'fixed_asset_id.exists' => 'The selected fixed asset is invalid.',
            'accountable.required' => 'The accountable field is required.',
            'company_id.required' => 'The company field is required.',
            'company_id.exists' => 'The selected company is invalid.',
            'business_unit_id.required' => 'The business unit field is required.',
            'business_unit_id.exists' => 'The selected business unit is invalid.',
            'department_id.required' => 'The department field is required.',
            'department_id.exists' => 'The selected department is invalid.',
            'unit_id.required' => 'The unit field is required.',
            'unit_id.exists' => 'The selected unit is invalid.',
            'subunit_id.required' => 'The subunit field is required.',
            'subunit_id.exists' => 'The selected subunit is invalid.',
            'location_id.required' => 'The location field is required.',
            'location_id.exists' => 'The selected location is invalid.',
            'attachments.file' => 'The attachments must be a file.',
            'attachments.mimes' => 'The attachments must be a file of type: pdf, doc, docx, xls, xlsx.',
            'attachments.max' => 'The attachments must not exceed 10 MB.'
        ];
    }
}
