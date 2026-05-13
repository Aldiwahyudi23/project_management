<?php

namespace App\Http\Resources\Api\Inspection;

use App\Models\MasterData\UserInspectionTemplate;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

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
            'document' => [
               'inspection_code' => $external['inspection_code'] ?? Null,
                'has_document' => $external['has_document'] ?? Null,
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
            'template_form' => $this->resolveTemplateForm($external),

            'template_report' => $this->resolveTemplateReport($external),
        ];
    }

/**
 * Resolve template form
 */
protected function resolveTemplateForm($external)
{
    $template = $external['template_form'] ?? null;
    $isEditable = $this->isFormTemplateEditable();
    
    // Ambil semua template user
    $userTemplates = UserInspectionTemplate::query()
        ->where('user_id',  Auth::id())
        ->where('template_type', UserInspectionTemplate::TYPE_FORM)
        ->where('is_active', true)
        ->get();

    // Jika tidak ada data dari external dan status tidak editable
    if (!$template && !$isEditable) {
        return null;
    }

    $response = [];

    // Handle selected
    if ($template && $userTemplate = $userTemplates->firstWhere('template_id', $template['id'])) {
        // User memiliki template yang dipilih
        $response['selected'] = [
            'id' => $template['id'],
            'name' => $userTemplate->name,
        ];
    } elseif ($template) {
        // Ada data dari external tapi user tidak punya template tersebut
        $response['selected'] = [
            'id' => $template['id'],
            'name' => $template['name'] ?? null,
        ];
    } else {
        // Tidak ada data dari external, biarkan null
        $response['selected'] = null;
    }

    $response['is_editable'] = $isEditable;

    // Tampilkan options hanya jika editable dan user memiliki template
    if ($isEditable && $userTemplates->isNotEmpty()) {
        $response['options'] = $userTemplates->map(function ($item) {
            return [
                'id' => $item->template_id,
                'name' => $item->name,
            ];
        })->values();
    }

    return $response;
}

/**
 * Resolve template report
 */
protected function resolveTemplateReport($external)
{
    $template = $external['template_report'] ?? null;
    $isEditable = $this->isReportTemplateEditable();
    
    // Ambil semua template user
    $userTemplates = UserInspectionTemplate::query()
        ->where('user_id', Auth::id())
        ->where('template_type', UserInspectionTemplate::TYPE_REPORT)
        ->where('is_active', true)
        ->get();

    // Jika tidak ada data dari external dan status tidak editable
    if (!$template && !$isEditable) {
        return null;
    }

    $response = [];

    // Handle selected
    if ($template && $userTemplate = $userTemplates->firstWhere('template_id', $template['id'])) {
        // User memiliki template yang dipilih
        $response['selected'] = [
            'id' => $template['id'],
            'name' => $userTemplate->name,
        ];
    } elseif ($template) {
        // Ada data dari external tapi user tidak punya template tersebut
        $response['selected'] = [
            'id' => $template['id'],
            'name' => $template['name'] ?? null,
        ];
    } else {
        // Tidak ada data dari external, biarkan null
        $response['selected'] = null;
    }

    $response['is_editable'] = $isEditable;

    // Tampilkan options hanya jika editable dan user memiliki template
    if ($isEditable && $userTemplates->isNotEmpty()) {
        $response['options'] = $userTemplates->map(function ($item) {
            return [
                'id' => $item->template_id,
                'name' => $item->name,
            ];
        })->values();
    }

    return $response;
}

    /**
     * Check form template editable
     */
    protected function isFormTemplateEditable(): bool
    {
        return in_array($this->status, [
            'draft',
            'pending',
            'accepted',
            'on_the_way',
            'arrived',
            'in_progress',
        ]);
    }

    /**
     * Check report template editable
     */
    protected function isReportTemplateEditable(): bool
    {
        return in_array($this->status, [
            'draft',
            'pending',
            'accepted',
            'on_the_way',
            'arrived',
            'in_progress',
            'under_review',
        ]);
    }
}