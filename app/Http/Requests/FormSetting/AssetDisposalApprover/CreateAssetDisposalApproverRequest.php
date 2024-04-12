<?php

namespace App\Http\Requests\FormSetting\AssetDisposalApprover;

use App\Http\Requests\FormSettingBase\UnitApproverCreateBaseRequest;
use App\Models\AssetDisposalApprover;
use Illuminate\Foundation\Http\FormRequest;

class CreateAssetDisposalApproverRequest extends UnitApproverCreateBaseRequest
{

    public function __construct()
    {
        parent::__construct(new AssetDisposalApprover);
    }

    public function authorize()
    {
        return true;
    }


}
