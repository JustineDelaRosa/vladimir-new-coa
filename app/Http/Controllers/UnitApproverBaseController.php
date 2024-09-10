<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormSetting\AssetTransferApprover\CreateAssetTransferApproverRequest;
use App\Http\Requests\FormSetting\AssetTransferApprover\UpdateAssetTransferApproverRequest;
use App\Models\AssetTransferApprover;
use App\Traits\FormSettingHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class UnitApproverBaseController extends Controller
{
    use ApiResponse, FormSettingHandler;

    protected $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    protected function getCreateFormRequest()
    {
        return FormRequest::class;
    }

    protected function getUpdateFormRequest()
    {
        return FormRequest::class;
    }

    public function index(Request $request)
    {
        return $this->formSettingIndex($request, new $this->model);
    }

    public function store()
    {
        $formRequestClass = $this->getCreateFormRequest();
        $request = app($formRequestClass);
        return $this->formSettingStore($request, new $this->model);
    }

    public function destroy($subUnitId)
    {
        return $this->formSettingDestroy(new $this->model, $subUnitId);
    }

    public function arrangeLayer($id)
    {
        $formRequestClass = $this->getUpdateFormRequest();
        $request = app($formRequestClass);
        return $this->formSettingArrangeLayer($request, new $this->model, $id);
    }
}
