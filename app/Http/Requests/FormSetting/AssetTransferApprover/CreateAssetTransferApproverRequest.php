<?php

namespace App\Http\Requests\FormSetting\AssetTransferApprover;

use App\Http\Requests\FormSettingBase\UnitApproverCreateBaseRequest;
use App\Models\AssetTransferApprover;
use App\Rules\SubunitApproverExists;
use Illuminate\Foundation\Http\FormRequest;

class CreateAssetTransferApproverRequest extends UnitApproverCreateBaseRequest
{
    public function __construct()
    {
        parent::__construct(new AssetTransferApprover);
    }

    public function authorize(): bool
    {
        return true;
    }
}
