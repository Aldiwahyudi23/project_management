<?php

namespace App\Http\Resources\Api\Inspection;

use Illuminate\Http\Resources\Json\JsonResource;

class JobDetailResource extends JsonResource
{
    public function toArray($request)
    {
        $external = $this->external_api['data'] ?? null;
        $vehicle  = $external['vehicle'] ?? null;

        return [
            'id' => $this->id,
            'inspection_id' => $this->inspection_id,
            'reference' => $this->reference,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'status_icon' => $this->status_icon,
            // 'inspection_date' => $this->inspection_date?->format('Y-m-d H:i:s'),
            'inspection_date_formatted' => $this->inspection_date?->translatedFormat('l, d F Y H:i'),
            'notes' => $this->notes,
            // 'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            // 'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'customer' => [
                // 'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'phone' => $this->customer?->phone,
                'address' => $this->customer?->address,
            ],

            'seller' => [
                'name'    => $this->seller?->unit_holder_name,
                'phone'   => $this->seller?->unit_holder_phone,
            ],

            'address' => [
                'area'    => $this->seller?->inspection_area,
                'name'    => $this->seller?->inspection_address,
                'link_maps'   => $this->seller?->link_maps,
            ],

            'inspector' => [
                // 'id' => $this->inspector?->id,
                'name' => $this->inspector?->name,
                'phone' => $this->inspector?->phone,
            ],

            'submitted_by' => [
                // 'id' => $this->submittedBy?->id,
                'name' => $this->submittedBy?->name,
                'phone' => $this->submittedBy?->phone,
            ],

            'vehicle' => [
                'license_plate' => $vehicle['license_plate'] ?? null,
                'vehicle_name'  => $vehicle['vehicle_name'] ?? null,
                
                'brand' => $vehicle['brand'] ?? null,
                'model' => $vehicle['model'] ?? null,
                'type'  => $vehicle['type'] ?? null,
                'cc'  => $vehicle['cc'] ?? null,
                'year'  => $vehicle['year'] ?? null,
                'transmission'  => $vehicle['transmission'] ?? null,
                'fuel_type'  => $vehicle['fuel_type'] ?? null,
                'generation'  => $vehicle['generation'] ?? null,
                'origin'  => $vehicle['origin'] ?? null,
                'market_period'  => $vehicle['market_period'] ?? null,
            ],
            'template' => $external['template'] ?? null,
        ];
    }
}