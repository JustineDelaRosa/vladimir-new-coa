<?php

namespace App\Rules;

use App\Models\Location;
use App\Models\SubUnit;
use Illuminate\Contracts\Validation\Rule;

class ValidLocationCode implements Rule
{
    private string $errorMessage;

    private $subunitCode;
    private $locationName;

    public function __construct($subunitCode, $locationName)
    {
        $this->subunitCode = $subunitCode;
        $this->locationName = $locationName;
    }

    public function passes($attribute, $value)
    {
        $inactive = Location::where('location_code', $value)->where('is_active', 0)->first();
        if ($inactive) {
            $this->errorMessage = 'The location is inactive';
            return false;
        }

        $location = Location::query()
            ->where('location_code', $value)
            ->where('location_name', $this->locationName)
            ->where('is_active', '!=', 0)
            ->first();

        if (!$location) {
            $this->errorMessage = 'The location does not exist';
            return false;
        }

        $subunitSyncId = SubUnit::where('sub_unit_code', $this->subunitCode)->first()->sync_id ?? 0;
        $associatedLocationSyncId = $location->subunit->pluck('sync_id')->toArray();
        if(!in_array($subunitSyncId, $associatedLocationSyncId)) {
            $this->errorMessage = 'The location does not belong to the subunit';
            return false;
        }
        return (bool)$location;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->errorMessage;
    }
}
