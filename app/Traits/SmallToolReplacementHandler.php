<?php

namespace App\Traits;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait SmallToolReplacementHandler
{
    public function replacementSTDataViewing($data)
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
        return $data->transform(function ($item) {
            return $this->response($item);
        });
    }

    private function nonCollectionData($data)
    {
        return $data->getCollection()->transform(function ($item) {
            return $this->response($item);
        });
    }

    private function transformItem($data): array
    {
        return $this->response($data);
    }


    private function response($data)
    {
        return [
            'id' => $data->id,
            'item' => $data->item->item_name,
            'item_code' => $data->item->item_code,
            'vladimir_tag_number' => $data->fixedAsset->vladimir_tag_number,
            'pr_number' => $data->pr_number,
            'po_number' => $data->po_number,
            'rr_number' => $data->rr_number,
            'status_description' => $data->status_description,
            'quantity' => $data->quantity,
            'is_active' => $data->is_active,
            'to_release' => $data->to_release,
            'created_at' => $data->created_at,
            'updated_at' => $data->updated_at,
        ];

    }

}
