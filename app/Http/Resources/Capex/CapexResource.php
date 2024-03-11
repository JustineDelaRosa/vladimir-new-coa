<?php

namespace App\Http\Resources\Capex;

use Illuminate\Http\Resources\Json\JsonResource;

class CapexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' =>$this->id,
            'capex' =>$this->capex,
            'project_name' =>$this->project_name,
        ];
    }
}
