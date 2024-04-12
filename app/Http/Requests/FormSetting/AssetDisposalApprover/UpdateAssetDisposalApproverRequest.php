<?php

namespace App\Http\Requests\FormSetting\AssetDisposalApprover;

use App\Http\Requests\FormSettingBase\UnitApproverUpdateBaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetDisposalApproverRequest extends UnitApproverUpdateBaseRequest
{
    public function __construct()
    {
        parent::__construct();
    }

    public function authorize()
    {
        return true;
    }
}
