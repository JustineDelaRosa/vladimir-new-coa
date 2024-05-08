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
        $subUnit = SubUnit::query()->find($value);
        if (!$subUnit || !$subUnit->is_active) {
            $this->errorMessage = 'The subunit does not exist or is not active';
            return false;
        }
        if ($this->requesting) {

            if(Route::currentRouteNamed('asset-transfer.store')){
                if ($subUnit->departmentUnitApprovers->isEmpty()) {
                    $this->errorMessage = 'No approvers assigned to the selected subunit.';
                    return false;
                }
            }elseif(Route::currentRouteNamed('asset-transfer-container.store')){
                if ($subUnit->transferApprovers->isEmpty()) {
                    $this->errorMessage = 'No transfer approvers assigned to the selected subunit.';
                    return false;
                }
            }elseif (Route::currentRouteNamed('asset-transfer.update')) {
                if ($this->assetTransferRequestId !== null && $subUnit->departmentUnitApprovers->isEmpty()) {
                    $this->errorMessage = 'No approvers assigned to the selected subunit.';
                    return false;
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
