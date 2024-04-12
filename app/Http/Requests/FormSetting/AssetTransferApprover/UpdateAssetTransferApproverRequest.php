<?php

namespace App\Http\Requests\FormSetting\AssetTransferApprover;

use App\Http\Requests\FormSettingBase\UnitApproverUpdateBaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetTransferApproverRequest extends UnitApproverUpdateBaseRequest
{
    public function __construct()
    {
        parent::__construct();
    }
}
