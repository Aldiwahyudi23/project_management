<?php

namespace App\Http\Resources\Api\Inspection;

use Illuminate\Http\Resources\Json\JsonResource;

class JobListResource extends JsonResource
{
    public function toArray($request)
    {
        // Ambil dari kolom settings (json)
        $settings = $this->settings ?? [];

        return [
            'uuid' => $this->uuid,
            'id' => $this->id,
            'inspection_id' => $this->inspection_id,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'status_icon' => $this->status_icon,
            

            // 'notes' => $this->notes,

            'label' => [
                'license_plate' => $settings['license_plate'] ?? null,
                'vehicle_name'  => $settings['vehicle_name'] ?? null,
                'inspection_date_formatted' =>
                    $this->inspection_date?->translatedFormat('l, d F Y H:i'),
            ],

            // 'customer' => [
            //     // 'id' => $this->customer?->id,
            //     'name' => $this->customer?->name,
            //     'phone' => $this->customer?->phone,
            //     'address' => $this->customer?->address,
            // ],

            // 'seller' => [
            //     'name'    => $this->seller?->unit_holder_name,
            //     'phone'   => $this->seller?->unit_holder_phone,
            // ],


            // 'inspector' => [
            //     // 'id' => $this->inspector?->id,
            //     'name' => $this->inspector?->name,
            //     'phone' => $this->inspector?->phone,
            // ],

        ];
    }
}