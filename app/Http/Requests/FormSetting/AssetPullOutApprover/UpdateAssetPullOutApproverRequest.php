<?php

namespace App\Http\Requests\FormSetting\AssetPullOutApprover;

use App\Http\Requests\FormSettingBase\UnitApproverUpdateBaseRequest;
use App\Models\AssetTransferApprover;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetPullOutApproverRequest extends UnitApproverUpdateBaseRequest
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
