<?php

namespace App\Http\Resources\Api\Inspection;

use Illuminate\Http\Resources\Json\JsonResource;

class JobListResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,

            'id' => $this->id,

            'inspection_id' => $this->inspection_id,

            'status' => $this->status,

            'status_label' => $this->status_label,

            'status_color' => $this->status_color,

            'status_icon' => $this->status_icon,

            /*
            |--------------------------------------------------------------------------
            | LABEL
            |--------------------------------------------------------------------------
            | Data dari Backend A
            | diinject saat enrichJobsWithInspectionData()
            */
            'label' => [

                'license_plate' => $this->license_plate,

                'vehicle_name' => $this->vehicle_name,

                'inspection_date_formatted' =>
                    $this->inspection_date?->translatedFormat(
                        'l, d F Y H:i'
                    ),
            ],

            /*
            |--------------------------------------------------------------------------
            | OPTIONAL
            |--------------------------------------------------------------------------
            */

            // 'notes' => $this->notes,

            // 'customer' => [
            //     'name' => $this->customer?->name,
            //     'phone' => $this->customer?->phone,
            //     'address' => $this->customer?->address,
            // ],

            // 'seller' => [
            //     'name' => $this->seller?->unit_holder_name,
            //     'phone' => $this->seller?->unit_holder_phone,
            // ],

            // 'inspector' => [
            //     'name' => $this->inspector?->name,
            //     'phone' => $this->inspector?->phone,
            // ],
        ];
    }
}