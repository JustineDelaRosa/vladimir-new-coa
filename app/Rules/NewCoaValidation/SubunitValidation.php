<?php

namespace App\Rules\NewCoaValidation;

use App\Models\SubUnit;
use App\Models\Unit;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Route;

class SubunitValidation implements Rule
{
    private $unitId;
    private $requesting;

    private $assetTransferRequestId;
    private string $errorMessage;

    public function __construct($unitId, $requesting, $assetTransferRequestId = null)
    {
        $this->unitId = $unitId;
        $this->requesting = $requesting;
        $this->assetTransferRequestId = $assetTransferRequestId;
    }

    public function passes($attribute, $value)
    {
        if(!$value) {
            return true;
        }
        $subUnit = SubUnit::query()->find($value);
        if (!$subUnit || !$subUnit->is_active) {
            $this->errorMessage = 'The subunit does not exist or is not active';
            return false;
        }
        if ($this->requesting) {

            $routesToApprovers = [
                'asset-transfer.store' => 'transferApprovers',
                'asset-transfer-container.store' => 'transferApprovers',
                'asset-transfer.update' => 'departmentUnitApprovers',
                'request-container.store' => 'departmentUnitApprovers',
                'update-container' => 'departmentUnitApprovers',
                'update-request' => 'departmentUnitApprovers',
            ];

            foreach ($routesToApprovers as $route => $approverType) {
                if (Route::currentRouteNamed($route)) {
                    $approvers = $subUnit->$approverType;
                    if ($approverType === 'departmentUnitApprovers' && $route === 'asset-transfer.update' && $this->assetTransferRequestId !== null) {
                        if ($approvers->isEmpty()) {
                            $this->errorMessage = 'No approvers assigned to the selected subunit.';
                            return false;
                        }
                    } elseif ($approvers->isEmpty()) {
                        $this->errorMessage = 'No approvers assigned to the selected subunit.';
                        return false;
                    }
                }
            }
        }

        $unit = Unit::query()->with('subunits')->where('id', $this->unitId)->first();
        $subUnit = SubUnit::query()->where('id', $value)->first();
        if (!$unit->subunits->contains($subUnit)) {
            $this->errorMessage = 'The subunit does not belong to the selected unit';
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->errorMessage;
    }
}
