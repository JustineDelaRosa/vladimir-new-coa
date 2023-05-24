<?php

namespace App\Exports;

use App\Models\FixedAsset;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MasterlistExport implements
    FromQuery,
    ShouldAutoSize,
    withMapping,
    WithHeadings,
    WithColumnFormatting,
    WithEvents,
    WithStrictNullComparison
{
    use Exportable;

    protected $search, $startDate, $endDate;

    public function __construct($search = null, $startDate = null, $endDate = null)
    {
        $this->search = $search;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }


    public function query()
    {
        $search = $this->search;
        $startDate = $this->startDate;
        $endDate = $this->endDate;
            $fixedAsset = FixedAsset::query()
                ->with('formula')
                ->with('majorCategory')
                ->with('minorCategory')
                ->with('division')
                ->with('company')
                ->with('department')
                ->with('location')
                ->with('accountTitle')
                ->when($search, function ($query, $search) {
                      return  $query->where('capex', 'LIKE', '%' . $search . '%')
                            ->orWhere('project_name', 'LIKE', '%' . $search . '%')
                            ->orWhere('vladimir_tag_number', 'LIKE', '%' . $search . '%')
                            ->orWhere('tag_number', 'LIKE', '%' . $search . '%')
                            ->orWhere('tag_number_old', 'LIKE', '%' . $search . '%')
//                            ->orWhere('asset_description', 'LIKE', '%' . $search . '%')
                            ->orWhere('type_of_request', 'LIKE', '%' . $search . '%')
                            ->orWhere('accountability', 'LIKE', '%' . $search . '%')
                            ->orWhere('accountable', 'LIKE', '%' . $search . '%')
                            ->orWhere('brand', 'LIKE', '%' . $search . '%')
                            ->orWhere('depreciation_method', 'LIKE', '%' . $search . '%')
                            ->orWhereHas('formula', function ($query) use ($search) {
                                $query->where('depreciation_method', 'LIKE', '%' . $search . '%');
                            })
                            ->orWhereHas('majorCategory', function ($query) use ($search) {
                                $query->where('major_category_name', 'LIKE', '%' . $search . '%');
                            })
                            ->orWhereHas('minorCategory', function ($query) use ($search) {
                                $query->where('minor_category_name', 'LIKE', '%' . $search . '%');
                            })
                            ->orWhereHas('division', function ($query) use ($search) {
                                $query->where('division_name', 'LIKE', '%' . $search . '%');
                            })
                            ->orWhereHas('company', function ($query) use ($search) {
                                $query->where('company_name', 'LIKE', '%' . $search . '%');
                            })
                            ->orWhereHas('department', function ($query) use ($search) {
                                $query->where('department_name', 'LIKE', '%' . $search . '%');
                            })
                            ->orWhereHas('location', function ($query) use ($search) {
                                $query->where('location_name', 'LIKE', '%' . $search . '%');
                            })
                            ->orWhereHas('accountTitle', function ($query) use ($search) {
                                $query->where('account_title_name', 'LIKE', '%' . $search . '%');
                            });

                })
                ->when($startDate, function ($query, $startDate) {
                    return $query->where('created_at', '>=', $startDate);
                })
                ->when($endDate, function ($query, $endDate) {
                    return $query->where('created_at', '<=', $endDate);
                })
                ->orderBy('id', 'asc');
        return $fixedAsset;
    }

    public function map($fixedAsset): array
    {
        return [
            $fixedAsset->capex,
            $fixedAsset->project_name,
            $fixedAsset->vladimir_tag_number,
            $fixedAsset->tag_number,
            $fixedAsset->tag_number_old,
            $fixedAsset->asset_description,
            $fixedAsset->type_of_request,
            $fixedAsset->asset_specification,
            $fixedAsset->accountability,
            $fixedAsset->accountable,
            $fixedAsset->cellphone_number,
            $fixedAsset->brand,
            $fixedAsset->division->division_name,
            $fixedAsset->majorCategory->major_category_name,
            $fixedAsset->minorCategory->minor_category_name,
            $fixedAsset->voucher,
            $fixedAsset->receipt,
            $fixedAsset->quantity,
            $fixedAsset->depreciation_method,
            $fixedAsset->est_useful_life,
            $fixedAsset->acquisition_date,
            $fixedAsset->acquisition_cost,
            $fixedAsset->formula->scrap_value,
            $fixedAsset->formula->original_cost,
            $fixedAsset->formula->accumulated_cost,
            $fixedAsset->is_active,
            $fixedAsset->care_of,
            $fixedAsset->formula->age,
            $fixedAsset->formula->end_depreciation,
            $fixedAsset->formula->depreciation_per_year,
            $fixedAsset->formula->depreciation_per_month,
            $fixedAsset->formula->remaining_book_value,
            $fixedAsset->formula->start_depreciation,
            $fixedAsset->company->company_code,
            $fixedAsset->company->company_name,
            $fixedAsset->department->department_code,
            $fixedAsset->department->department_name,
            $fixedAsset->location->location_code,
            $fixedAsset->location->location_name,
            $fixedAsset->accountTitle->account_title_code,
            $fixedAsset->accountTitle->account_title_name,
        ];
    }

    public function headings(): array
    {
        return [
            'Capex',
            'Project Name',
            'Vladimir Tag Number',
            'Tag Number',
            'Tag Number Old',
            'Asset Description',
            'Type of Request',
            'Asset Specification',
            'Accountability',
            'Accountable',
            'Cellphone Number',
            'Brand',
            'Division',
            'Major Category',
            'Minor Category',
            'Voucher',
            'Receipt',
            'Quantity',
            'Depreciation Method',
            'Est. Useful Life',
            'Acquisition Date',
            'Acquisition Cost',
            'Scrap Value',
            'Original Cost',
            'Accumulated Cost',
            'Status',
            'Care Of',
            'Age',
            'End of Depreciation',
            'Depreciation Per Year',
            'Depreciation Per Month',
            'Remaining Book Value',
            'Start Depreciation',
            'Company Code',
            'Company Name',
            'Department Code',
            'Department Name',
            'Location Code',
            'Location Name',
            'Account Title Code',
            'Account Title Name',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => '0',
            'D' => '0',
            'E' => '0',
            'V' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'W' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'X' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'Y' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'Z' => '0',
            'AD' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'AE' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'AF' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'AG' => 'YYYY',
        ];
    }

    public function registerEvents(): array
    {

        return[
          AfterSheet::class => function(AfterSheet $event){
              $event->sheet->getStyle('A1:AO1')->applyFromArray([
                  'font' => [
                      'bold' => true
                  ],
                  'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                  ],
                  'fill' => [
                      'fillType' => Fill::FILL_SOLID,
                      'startColor' => [
                          //color yellow
                          'rgb' => 'FFFF00'
                      ],
                  ],
                  //border every cell of the header
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => '0000'],
                        ],
                    ],
              ]);
              $lastRow = $event->sheet->getHighestRow();
              //align center as long as there is data in the cell
              $event->sheet->getStyle('A2:AO'.$lastRow)->applyFromArray([
                  'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                  ],
              ]);
          }
        ];
    }


}
