<?php

namespace App\Imports;

use App\Models\Capex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Events\BeforeImport;

class CapexImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable;

    public function startRow(): int
    {
        // TODO: Implement startRow() method.
        return 2;
    }

    /**
     * @throws ValidationException
     */
    public function collection(Collection $collection)
    {
        Validator::make($collection->toArray(), $this->rules($collection->toArray()), $this->messages())->validate();

        foreach ($collection as $row) {
            if ($row['capex'] == $row['sub_capex']) {
                $capex = Capex::create([
                    'capex' => $row['capex'],
                    'project_name' => $row['project_name'],
                    'is_active' => true,
                ]);
            } else {
                $capex = Capex::withTrashed()->where('capex', $row['capex'])->first();
                $subCapex = $capex->subCapex()->create([
                    'sub_capex' => $row['sub_capex'],
                    'sub_project' => $row['sub_project'],
                    'is_active' => !$capex->deleted_at,
                ]);
                if ($capex->deleted_at) {
                    $subCapex->delete();
                }
            }
        }
    }

    private function rules($collection): array
    {
        return [
            '*.capex' => [
                'required',
                'regex:/^[0-9-]+$/',
                function ($attribute, $value, $fail) use ($collection) {
                    // Static variable to keep track of duplicates
                    $index = array_search($value, array_column($collection, 'capex'));
                    $subCapex = $collection[$index]['sub_capex'];

                    static $duplicateCapexFound = [];

                    // If this value has already been found as a duplicate, don't fail the validation again
                    if (in_array($value, $duplicateCapexFound)) {
                        return;
                    }

                    // Collect capex values where capex is same as sub_capex
                    $capexArray = array_filter($collection, function($row) use ($value) {
                        return $row['capex'] === $row['sub_capex'] && $row['capex'] === $value;
                    });

                    // Retry capex values out of our filtered data
                    $capexArray = array_column($capexArray, 'capex');

                    // Count how many times each capex value appears
                    $capexArrayCounts = array_count_values($capexArray);

                    // Retain capex values that appear more than once (duplicates)
                    $duplicates = array_filter($capexArrayCounts, function($count) {
                        return $count > 1;
                    });

                    if (!empty($duplicates)) {
                        $duplicates = implode(', ', array_keys($duplicates));
                        $fail('Capex duplicates found with the value of: ' . $duplicates);
                        $duplicateCapexFound[] = $value;
                    }

                    if ($value === $subCapex) {
                        $capex = Capex::withTrashed()->where('capex', $value)->first();
                        if ($capex) {
                            $fail('Capex already exists');
                        }
                    } else {
                        $capex = Capex::withTrashed()->where('capex', $value)->first();
                        if (!$capex) {
                            $fail('Capex does not exist');
                        }
                    }
                }
            ],
            '*.project_name' => 'required',
            '*.sub_capex' => ['required',
                function ($attribute, $value, $fail) use ($collection) {
                    $index = array_search($value, array_column($collection, 'sub_capex'));
                    $capex = $collection[$index]['capex'];
                    $subCapex = $collection[$index]['sub_capex'];

                    static $duplicateSubCapexFound = [];

                    if (in_array($value, $duplicateSubCapexFound)) {
                        return;
                    }

                    $subCapexArray = array_filter($collection, function($row) use ($value) {
                        return $row['sub_capex'] === $value && $row['capex'] !== $row['sub_capex'];
                    });

                    $subCapexArray = array_column($subCapexArray, 'sub_capex');
                    $subCapexArrayCounts = array_count_values($subCapexArray);
                    $duplicates = array_filter($subCapexArrayCounts, function($count) {
                        return $count > 1;
                    });

                    if (!empty($duplicates)) {
                        $duplicates = implode(', ', array_keys($duplicates));
                        $fail('Sub Capex duplicates found with the value of: ' . $duplicates);
                        $duplicateSubCapexFound[] = $value;
                    }

                    if ($capex !== $value) {
                        if (!preg_match('/-[A-Za-z]/', $value)) {
                            $fail('Invalid sub capex format');
                        }
                    }

                    $capex = Capex::where('capex', $capex)->first();
                    if ($capex) {
                        $subCapex = $capex->subCapex()->withTrashed()->where('sub_capex', $subCapex)->first();
                        if ($subCapex) {
                            $fail('Sub capex already exists');
                        }
                    }
                }
            ],
            '*.sub_project' => 'required',
        ];
    }

    private function messages(): array
    {
        return [
            'capex.required' => 'Capex is required.',
            'capex.regex' => 'Capex should not have a letter.',
            'project_name.required' => 'Project name is required.',
            'sub_capex.required' => 'Sub capex is required.',
            'sub_project.required' => 'Sub project is required.',
        ];
    }
}
