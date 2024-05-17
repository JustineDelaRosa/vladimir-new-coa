<?php

namespace App\Http\Requests\AssetTransfer;

use App\Models\AssetMovementContainer\AssetTransferContainer;
use App\Models\AssetTransferRequest;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
use Illuminate\Foundation\Http\FormRequest;

class CreateAssetTransferRequestRequest extends FormRequest
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
            'assets.*.fixed_asset_id' => ['required', 'exists:fixed_assets,id', function ($attribute, $value, $fail) {

                // Get all fixed_asset_id values
                $fixedAssetIds = array_column($this->input('assets'), 'fixed_asset_id');

                // Check if there's a duplicate
                if (count($fixedAssetIds) !== count(array_unique($fixedAssetIds))) {
                    $fail('Duplicate fixed asset found');
                }
                //check if the fixed asset is already in asset transfer container
//                $assetTransferRequest = AssetTransferRequest::where('fixed_asset_id', $value)
//                    ->where('status', '!=', 'Approved')->first();
//                if ($assetTransferRequest) {
//                    $fail('The fixed asset is already in an asset transfer request');
//                }
            }],
            'company_id' => 'required|exists:companies,id',
            'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, true)],
            'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
            'accountability' => 'required|in:Common,Personal Issued',
            'accountable' => 'nullable|required_if:assetTransfer.*.accountability,Personal Issued',
            'remarks' => 'nullable',
            'description' => 'required',
            'attachments.*' => 'nullable|max:5120',
        ];
    }

    function messages()
    {
        return [
            'assetTransfer.*.fixed_asset_id.required' => 'Fixed Asset is required',
            'assetTransfer.*.fixed_asset_id.exists' => 'Fixed Asset does not exist',
            'assetTransfer.*.company_id.required' => 'Company is required',
            'assetTransfer.*.company_id.exists' => 'Company does not exist',
            'assetTransfer.*.business_unit_id.required' => 'Business Unit is required',
            'assetTransfer.*.business_unit_id.exists' => 'Business Unit does not exist',
            'assetTransfer.*.department_id.required' => 'Department is required',
            'assetTransfer.*.department_id.exists' => 'Department does not exist',
            'assetTransfer.*.unit_id.required' => 'Unit is required',
            'assetTransfer.*.unit_id.exists' => 'Unit does not exist',
            'assetTransfer.*.subunit_id.required' => 'Subunit is required',
            'assetTransfer.*.subunit_id.exists' => 'Subunit does not exist',
            'assetTransfer.*.location_id.required' => 'Location is required',
            'assetTransfer.*.location_id.exists' => 'Location does not exist',
            'assetTransfer.*.accountability.required' => 'Accountability is required',
            'assetTransfer.*.accountability.in' => 'Accountability must be Common or Personal Issued',
            'assetTransfer.*.accountable.required_if' => 'Accountable is required',
            'attachments.mimes' => 'Attachment must be a file of type: pdf, jpg, jpeg, png, doc, docx, xls, xlsx',
            'attachments.max' => 'Attachment must not be greater than 5MB',
        ];
    }
}
