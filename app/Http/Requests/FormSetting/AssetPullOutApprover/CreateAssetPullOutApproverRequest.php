<?php

namespace App\Http\Requests\FormSetting\AssetPullOutApprover;

use App\Http\Requests\FormSettingBase\UnitApproverCreateBaseRequest;
use App\Models\AssetPullOutApprover;
use Illuminate\Foundation\Http\FormRequest;

class CreateAssetPullOutApproverRequest extends UnitApproverCreateBaseRequest
{
    public function __construct()
    {
        parent::__construct(new AssetPullOutApprover);
    }

    public function authorize()
    {
        return true;
    }

}
