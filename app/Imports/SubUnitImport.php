<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\SubUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;

class SubUnitImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable;

    public function startRow(): int
    {
        return 2;
    }

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        Validator::make($collection->toArray(), $this->rules($collection->toArray()), $this->messages())->validate();

        foreach ($collection as $row){
            SubUnit::create([
                'department_id' => Department::where('department_name', $row['department'])->first()->id,
                'sub_unit_code' => $row['sub_unit_code'],
                'sub_unit_name' => $row['sub_unit_name'],
            ]);
        }
    }

    private function rules($collection): array
    {
        return [
            '*.department' => [
                'required',
                'exists:departments,department_name',
            ],
            '*.sub_unit_code' => [
                'required',
            ],
            '*.sub_unit_name' => [
                'required',
                'unique:sub_units,sub_unit_name',
                function($attribute, $value, $fail) use ($collection){
                    //check for duplicate sub_unit_name into the collection
                    $duplicates = $collection->filter(function ($item) use ($value){
                        return $item['sub_unit_name'] == $value;
                    });
                    if ($duplicates->count() > 1){
                        $fail('Duplicate sub unit found');
                    }
                }
            ],
        ];
    }

    private function messages():array
    {
        return [
            '*.department.required' => 'Department is required',
            '*.department.exists' => 'Department not found',
            '*.sub_unit_code.required' => 'Sub Unit Code is required',
            '*.sub_unit_name.required' => 'Sub Unit Name is required',
            '*.sub_unit_name.unique' => 'Sub Unit Name already exists',
        ];
    }
}
