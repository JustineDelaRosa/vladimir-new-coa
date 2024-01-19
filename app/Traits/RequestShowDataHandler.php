<?php

namespace App\Traits;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait RequestShowDataHandler
{
    private function responseData($data)
    {
        if ($data instanceof Collection) {
            return $this->collectionData($data);
        } elseif ($data instanceof LengthAwarePaginator) {
            $data->getCollection()->transform(function ($item) {
                return $this->transformItem($item);
            });
            return $data;
        } else {
            return $this->nonCollectionData($data);
        }
    }

    private function collectionData($data)
    {
        return $data->transform(function ($ar) {

            /*
             * //TODO: This is the viewing if the attachments are multiple
             * $letterOfRequestMedia = $ar->getMedia('letter_of_request')->all();
             * */

            $letterOfRequestMedia = $ar->getMedia('letter_of_request')->first();
            $quotationMedia = $ar->getMedia('quotation')->first();
            $specificationFormMedia = $ar->getMedia('specification_form')->first();
            $toolOfTradeMedia = $ar->getMedia('tool_of_trade')->first();
            $otherAttachmentsMedia = $ar->getMedia('other_attachments')->first();

            return [
                'can_edit' => $ar->status == 'Returned' || $ar->status == 'For Approval of Approver 1' ? 1 : 0,
                'can_resubmit' => $ar->status == 'Returned' ? 1 : 0,
                'asset_approval_id' => $ar->assetApproval->first(function ($approval) {
                        return $approval->status == 'For Approval';
                    })->id ?? '',
                'id' => $ar->id,
                'status' => $ar->status,
                'transaction_number' => $ar->transaction_number,
                'reference_number' => $ar->reference_number,
                'pr_number' => $ar->pr_number,
                'po_number' => $ar->po_number,
                'attachment_type' => $ar->attachment_type,
                'remarks' => $ar->remarks ?? '',
                'accountability' => $ar->accountability,
                'accountable' => $ar->accountable ?? '-',
                'additional_info' => $ar->additional_info ?? '-',
                'acquisition_details' => $ar->acquisition_details ?? '-',
                'asset_description' => $ar->asset_description,
                'asset_specification' => $ar->asset_specification ?? '-',
                'cellphone_number' => $ar->cellphone_number ?? '-',
                'brand' => $ar->brand ?? '-',
                'quantity' => $ar->quantity,
                'ordered' => $ar->quantity,
                'delivered' => $ar->quantity_delivered ?? '-',
                'remaining' => $ar->quantity - $ar->quantity_delivered ?? '-',
                // 'unit_price' => $ar->unit_price ?? '-',
                // 'delivery_date' => $ar->delivery_date ?? '-',
                'requestor' => [
                    'id' => $ar->requestor->id,
                    'username' => $ar->requestor->username,
                    'employee_id' => $ar->requestor->employee_id,
                    'firstname' => $ar->requestor->firstname,
                    'lastname' => $ar->requestor->lastname,
                ],
                'type_of_request' => [
                    'id' => $ar->typeOfRequest->id,
                    'type_of_request_name' => $ar->typeOfRequest->type_of_request_name,
                ],
                'company' => [
                    'id' => $ar->company->id,
                    'company_code' => $ar->company->company_code,
                    'company_name' => $ar->company->company_name,
                ],
                'department' => [
                    'id' => $ar->department->id,
                    'department_code' => $ar->department->department_code,
                    'department_name' => $ar->department->department_name,
                ],
                'subunit' => [
                    'id' => $ar->subunit->id,
                    'subunit_code' => $ar->subunit->sub_unit_code,
                    'subunit_name' => $ar->subunit->sub_unit_name,
                ],
                'location' => [
                    'id' => $ar->location->id,
                    'location_code' => $ar->location->location_code,
                    'location_name' => $ar->location->location_name,
                ],
                'account_title' => [
                    'id' => $ar->accountTitle->id,
                    'account_title_code' => $ar->accountTitle->account_title_code,
                    'account_title_name' => $ar->accountTitle->account_title_name,
                ],
                'supplier' => [
                    'id' => $ar->supplier->id ?? '-',
                    'supplier_code' => $ar->supplier->supplier_code ?? '-',
                    'supplier_name' => $ar->supplier->supplier_name ?? '-',
                ],
                'attachments' => [
                    //TODO: This is the viewing if the attachments are multiple
                    /*
                     *
                      $letterOfRequestMedia ? collect($letterOfRequestMedia)->map(function ($media) {
                          return [
                              'id' => $media->id,
                              'file_name' => $media->file_name,
                              'file_path' => $media->getPath(),
                              'file_url' => $media->getUrl(),
                          ];
                      }) : '-',
                    */
                    'letter_of_request' => $letterOfRequestMedia ? [
                        'id' => $letterOfRequestMedia->id,
                        'file_name' => $letterOfRequestMedia->file_name,
                        'file_path' => $letterOfRequestMedia->getPath(),
                        'file_url' => $letterOfRequestMedia->getUrl(),
                    ] : null,
                    'quotation' => $quotationMedia ? [
                        'id' => $quotationMedia->id,
                        'file_name' => $quotationMedia->file_name,
                        'file_path' => $quotationMedia->getPath(),
                        'file_url' => $quotationMedia->getUrl(),
                    ] : null,
                    'specification_form' => $specificationFormMedia ? [
                        'id' => $specificationFormMedia->id,
                        'file_name' => $specificationFormMedia->file_name,
                        'file_path' => $specificationFormMedia->getPath(),
                        'file_url' => $specificationFormMedia->getUrl(),
                    ] : null,
                    'tool_of_trade' => $toolOfTradeMedia ? [
                        'id' => $toolOfTradeMedia->id,
                        'file_name' => $toolOfTradeMedia->file_name,
                        'file_path' => $toolOfTradeMedia->getPath(),
                        'file_url' => $toolOfTradeMedia->getUrl(),
                    ] : null,
                    'other_attachments' => $otherAttachmentsMedia ? [
                        'id' => $otherAttachmentsMedia->id,
                        'file_name' => $otherAttachmentsMedia->file_name,
                        'file_path' => $otherAttachmentsMedia->getPath(),
                        'file_url' => $otherAttachmentsMedia->getUrl(),
                    ] : null,
                ]
            ];
        });
    }
    private function nonCollectionData($data)
    {
        return $data->getCollection()->transform(function ($ar) {

            /*
             * //TODO: This is the viewing if the attachments are multiple
             * $letterOfRequestMedia = $ar->getMedia('letter_of_request')->all();
             * */

            $letterOfRequestMedia = $ar->getMedia('letter_of_request')->first();
            $quotationMedia = $ar->getMedia('quotation')->first();
            $specificationFormMedia = $ar->getMedia('specification_form')->first();
            $toolOfTradeMedia = $ar->getMedia('tool_of_trade')->first();
            $otherAttachmentsMedia = $ar->getMedia('other_attachments')->first();

            return [
                'can_edit' => $ar->status == 'Returned' || $ar->status == 'For Approval of Approver 1' ? 1 : 0,
                'can_resubmit' => $ar->status == 'Returned' ? 1 : 0,
                'asset_approval_id' => $ar->assetApproval->first(function ($approval) {
                        return $approval->status == 'For Approval';
                    })->id ?? '',
                'id' => $ar->id,
                'status' => $ar->status,
                'transaction_number' => $ar->transaction_number,
                'reference_number' => $ar->reference_number,
                'pr_number' => $ar->pr_number,
                'po_number' => $ar->po_number,
                'attachment_type' => $ar->attachment_type,
                'remarks' => $ar->remarks ?? '',
                'accountability' => $ar->accountability,
                'accountable' => $ar->accountable ?? '-',
                'additional_info' => $ar->additional_info ?? '-',
                'acquisition_details' => $ar->acquisition_details ?? '-',
                'asset_description' => $ar->asset_description,
                'asset_specification' => $ar->asset_specification ?? '-',
                'cellphone_number' => $ar->cellphone_number ?? '-',
                'brand' => $ar->brand ?? '-',
                'quantity' => $ar->quantity,
                'ordered' => $ar->quantity,
                'delivered' => $ar->quantity_delivered ?? '-',
                'remaining' => $ar->quantity - $ar->quantity_delivered ?? '-',
                // 'unit_price' => $ar->unit_price ?? '-',
                // 'delivery_date' => $ar->delivery_date ?? '-',
                'requestor' => [
                    'id' => $ar->requestor->id,
                    'username' => $ar->requestor->username,
                    'employee_id' => $ar->requestor->employee_id,
                    'firstname' => $ar->requestor->firstname,
                    'lastname' => $ar->requestor->lastname,
                ],
                'type_of_request' => [
                    'id' => $ar->typeOfRequest->id,
                    'type_of_request_name' => $ar->typeOfRequest->type_of_request_name,
                ],
                'company' => [
                    'id' => $ar->company->id,
                    'company_code' => $ar->company->company_code,
                    'company_name' => $ar->company->company_name,
                ],
                'department' => [
                    'id' => $ar->department->id,
                    'department_code' => $ar->department->department_code,
                    'department_name' => $ar->department->department_name,
                ],
                'subunit' => [
                    'id' => $ar->subunit->id,
                    'subunit_code' => $ar->subunit->sub_unit_code,
                    'subunit_name' => $ar->subunit->sub_unit_name,
                ],
                'location' => [
                    'id' => $ar->location->id,
                    'location_code' => $ar->location->location_code,
                    'location_name' => $ar->location->location_name,
                ],
                'account_title' => [
                    'id' => $ar->accountTitle->id,
                    'account_title_code' => $ar->accountTitle->account_title_code,
                    'account_title_name' => $ar->accountTitle->account_title_name,
                ],
                'supplier' => [
                    'id' => $ar->supplier->id ?? '-',
                    'supplier_code' => $ar->supplier->supplier_code ?? '-',
                    'supplier_name' => $ar->supplier->supplier_name ?? '-',
                ],
                'attachments' => [
                    //TODO: This is the viewing if the attachments are multiple
                    /*
                     *
                      $letterOfRequestMedia ? collect($letterOfRequestMedia)->map(function ($media) {
                          return [
                              'id' => $media->id,
                              'file_name' => $media->file_name,
                              'file_path' => $media->getPath(),
                              'file_url' => $media->getUrl(),
                          ];
                      }) : '-',
                    */
                    'letter_of_request' => $letterOfRequestMedia ? [
                        'id' => $letterOfRequestMedia->id,
                        'file_name' => $letterOfRequestMedia->file_name,
                        'file_path' => $letterOfRequestMedia->getPath(),
                        'file_url' => $letterOfRequestMedia->getUrl(),
                    ] : null,
                    'quotation' => $quotationMedia ? [
                        'id' => $quotationMedia->id,
                        'file_name' => $quotationMedia->file_name,
                        'file_path' => $quotationMedia->getPath(),
                        'file_url' => $quotationMedia->getUrl(),
                    ] : null,
                    'specification_form' => $specificationFormMedia ? [
                        'id' => $specificationFormMedia->id,
                        'file_name' => $specificationFormMedia->file_name,
                        'file_path' => $specificationFormMedia->getPath(),
                        'file_url' => $specificationFormMedia->getUrl(),
                    ] : null,
                    'tool_of_trade' => $toolOfTradeMedia ? [
                        'id' => $toolOfTradeMedia->id,
                        'file_name' => $toolOfTradeMedia->file_name,
                        'file_path' => $toolOfTradeMedia->getPath(),
                        'file_url' => $toolOfTradeMedia->getUrl(),
                    ] : null,
                    'other_attachments' => $otherAttachmentsMedia ? [
                        'id' => $otherAttachmentsMedia->id,
                        'file_name' => $otherAttachmentsMedia->file_name,
                        'file_path' => $otherAttachmentsMedia->getPath(),
                        'file_url' => $otherAttachmentsMedia->getUrl(),
                    ] : null,
                ]
            ];
        });
    }
    private function transformItem($ar): array
    {
        $letterOfRequestMedia = $ar->getMedia('letter_of_request')->first();
        $quotationMedia = $ar->getMedia('quotation')->first();
        $specificationFormMedia = $ar->getMedia('specification_form')->first();
        $toolOfTradeMedia = $ar->getMedia('tool_of_trade')->first();
        $otherAttachmentsMedia = $ar->getMedia('other_attachments')->first();

        return [
            'can_edit' => $ar->status == 'Returned' || $ar->status == 'For Approval of Approver 1' ? 1 : 0,
            'can_resubmit' => $ar->status == 'Returned' ? 1 : 0,
            'asset_approval_id' => $ar->assetApproval->first(function ($approval) {
                    return $approval->status == 'For Approval';
                })->id ?? '',
            'id' => $ar->id,
            'status' => $ar->status,
            'transaction_number' => $ar->transaction_number,
            'reference_number' => $ar->reference_number,
            'pr_number' => $ar->pr_number,
            'po_number' => $ar->po_number,
            'attachment_type' => $ar->attachment_type,
            'remarks' => $ar->remarks ?? '',
            'accountability' => $ar->accountability,
            'accountable' => $ar->accountable ?? '-',
            'additional_info' => $ar->additional_info ?? '-',
            'acquisition_details' => $ar->acquisition_details ?? '-',
            'asset_description' => $ar->asset_description,
            'asset_specification' => $ar->asset_specification ?? '-',
            'cellphone_number' => $ar->cellphone_number ?? '-',
            'brand' => $ar->brand ?? '-',
            'quantity' => $ar->quantity,
            'ordered' => $ar->quantity,
            'delivered' => $ar->quantity_delivered ?? '-',
            'remaining' => $ar->quantity - $ar->quantity_delivered ?? '-',
            // 'unit_price' => $ar->unit_price ?? '-',
            // 'delivery_date' => $ar->delivery_date ?? '-',
            'requestor' => [
                'id' => $ar->requestor->id,
                'username' => $ar->requestor->username,
                'employee_id' => $ar->requestor->employee_id,
                'firstname' => $ar->requestor->firstname,
                'lastname' => $ar->requestor->lastname,
            ],
            'type_of_request' => [
                'id' => $ar->typeOfRequest->id,
                'type_of_request_name' => $ar->typeOfRequest->type_of_request_name ?? '-',
            ],
            'company' => [
                'id' => $ar->company->id,
                'company_code' => $ar->company->company_code,
                'company_name' => $ar->company->company_name,
            ],
            'department' => [
                'id' => $ar->department->id,
                'department_code' => $ar->department->department_code,
                'department_name' => $ar->department->department_name,
            ],
            'subunit' => [
                'id' => $ar->subunit->id,
                'subunit_code' => $ar->subunit->sub_unit_code,
                'subunit_name' => $ar->subunit->sub_unit_name,
            ],
            'location' => [
                'id' => $ar->location->id,
                'location_code' => $ar->location->location_code,
                'location_name' => $ar->location->location_name,
            ],
            'account_title' => [
                'id' => $ar->accountTitle->id,
                'account_title_code' => $ar->accountTitle->account_title_code,
                'account_title_name' => $ar->accountTitle->account_title_name,
            ],
            'supplier' => [
                'id' => $ar->supplier->id ?? '-',
                'supplier_code' => $ar->supplier->supplier_code ?? '-',
                'supplier_name' => $ar->supplier->supplier_name ?? '-',
            ],
            'attachments' => [
                //TODO: This is the viewing if the attachments are multiple
                /*
                 *
                  $letterOfRequestMedia ? collect($letterOfRequestMedia)->map(function ($media) {
                      return [
                          'id' => $media->id,
                          'file_name' => $media->file_name,
                          'file_path' => $media->getPath(),
                          'file_url' => $media->getUrl(),
                      ];
                  }) : '-',
                */
                'letter_of_request' => $letterOfRequestMedia ? [
                    'id' => $letterOfRequestMedia->id,
                    'file_name' => $letterOfRequestMedia->file_name,
                    'file_path' => $letterOfRequestMedia->getPath(),
                    'file_url' => $letterOfRequestMedia->getUrl(),
                ] : null,
                'quotation' => $quotationMedia ? [
                    'id' => $quotationMedia->id,
                    'file_name' => $quotationMedia->file_name,
                    'file_path' => $quotationMedia->getPath(),
                    'file_url' => $quotationMedia->getUrl(),
                ] : null,
                'specification_form' => $specificationFormMedia ? [
                    'id' => $specificationFormMedia->id,
                    'file_name' => $specificationFormMedia->file_name,
                    'file_path' => $specificationFormMedia->getPath(),
                    'file_url' => $specificationFormMedia->getUrl(),
                ] : null,
                'tool_of_trade' => $toolOfTradeMedia ? [
                    'id' => $toolOfTradeMedia->id,
                    'file_name' => $toolOfTradeMedia->file_name,
                    'file_path' => $toolOfTradeMedia->getPath(),
                    'file_url' => $toolOfTradeMedia->getUrl(),
                ] : null,
                'other_attachments' => $otherAttachmentsMedia ? [
                    'id' => $otherAttachmentsMedia->id,
                    'file_name' => $otherAttachmentsMedia->file_name,
                    'file_path' => $otherAttachmentsMedia->getPath(),
                    'file_url' => $otherAttachmentsMedia->getUrl(),
                ] : null,
            ]

        ];
    }
}
