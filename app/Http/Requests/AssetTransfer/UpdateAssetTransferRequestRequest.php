<?php

namespace App\Http\Requests\AssetTransfer;

use App\Models\AssetMovementContainer\AssetTransferContainer;
use App\Models\AssetTransferRequest;
use App\Models\FixedAsset;
use App\Rules\AssetMovementCheck;
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
            'assets' => 'required|array',
//            'receiver_id' => 'required|exists:users,id',
            'assets.*.fixed_asset_id' => [new AssetMovementCheck(),'required', 'exists:fixed_assets,id', function ($attribute, $value, $fail) {

                // Get all fixed_asset_id values
                $fixedAssetIds = array_column($this->input('assets'), 'fixed_asset_id');

                // Check if there's a duplicate
                if (count($fixedAssetIds) !== count(array_unique($fixedAssetIds))) {
                    $fail('Duplicate fixed asset found');
                }

                /*$fixedAsset = FixedAsset::find($value);
                $fromSubunitId = request()->from_subunit_id ? request()->from_subunit_id : auth('sanctum')->user()->subunit_id;
                if ($fixedAsset->subunit_id != $fromSubunitId) {
                    $fail('Asset does not belong to this subunit');
                }*/
            }],
            'assets.*.receiver_id' => 'required|exists:users,id',

            'company_id' => 'required|exists:companies,id',
            'business_unit_id' => ['required', 'exists:business_units,id', new BusinessUnitValidation(request()->company_id)],
            'department_id' => ['required', 'exists:departments,id', new DepartmentValidation(request()->business_unit_id)],
            'unit_id' => ['required', 'exists:units,id', new UnitValidation(request()->department_id)],
            'subunit_id' => ['required', 'exists:sub_units,id', new SubunitValidation(request()->unit_id, true), function ($attribute, $value, $fail) {
//                $user = auth('sanctum')->user();
//                $userSubunit = $user->subunit_id;
//                if ($userSubunit == $value) {
//                    $fail('You are not allowed to transfer assets to this subunit');
//                }
            }],
            'location_id' => ['required', 'exists:locations,id', new LocationValidation(request()->subunit_id)],
//            'account_title_id' => ['required', 'exists:account_titles,id'],
//            'accountability' => 'required|in:Common,Personal Issued',
//            'accountable' => 'nullable|required_if:accountability,Personal Issued',
            'assets.*.accountability' => 'required|in:Common,Personal Issued',
            'assets.*.accountable' => 'nullable|required_if:assets.*.accountability,Personal Issued',
            'remarks' => 'nullable',
            'description' => 'required',
            'depreciation_debit_id' => 'required|exists:account_titles,id',
            'attachments' => 'nullable|max:5120',
        ];
    }

    function messages()
    {
        return [
            'assets.*.fixed_asset_id.required' => 'Fixed asset is required',
            'assets.*.fixed_asset_id.exists' => 'Fixed asset does not exist',
            'assets.*.receiver_id.required' => 'Receiver is required',
            'assets.*.receiver_id.exists' => 'Receiver does not exist',
            'company_id.required' => 'Company is required',
            'company_id.exists' => 'Company does not exist',
            'business_unit_id.required' => 'Business unit is required',
            'business_unit_id.exists' => 'Business unit does not exist',
            'department_id.required' => 'Department is required',
            'department_id.exists' => 'Department does not exist',
            'unit_id.required' => 'Unit is required',
            'unit_id.exists' => 'Unit does not exist',
            'subunit_id.required' => 'Subunit is required',
            'subunit_id.exists' => 'Subunit does not exist',
            'location_id.required' => 'Location is required',
            'location_id.exists' => 'Location does not exist',
            'account_title_id.required' => 'Account title is required',
            'account_title_id.exists' => 'Account title does not exist',
            'accountability.required' => 'Accountability is required',
            'accountability.in' => 'Accountability must be either Common or Personal Issued',
            'accountable.required_if' => 'Accountable is required',
            'remarks.required' => 'Remarks is required',
            'description.required' => 'Description is required',
            'attachments.mimes' => 'Attachment must be a file of type: pdf, jpg, jpeg, png, doc, docx, xls, xlsx',
            'attachments.max' => 'Attachment must not be greater than 10MB',
            'depreciation_debit_id.required' => 'Depreciation credit is required',
            'depreciation_debit_id.exists' => 'Depreciation credit does not exist',
        ];
    }
}
