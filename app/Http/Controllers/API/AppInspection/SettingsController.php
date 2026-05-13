<?php

namespace App\Http\Controllers\Api\AppInspection;

use App\Http\Controllers\Controller;
use App\Models\MasterData\UserInspectionTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Get all settings
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Settings retrieved successfully',
            'data' => [

                // 🔥 inspection templates
                'inspection_templates' => $this->getInspectionTemplates(),

                // nanti gampang tambah lagi
                // 'preferences' => $this->getPreferences(),
                // 'notifications' => $this->getNotifications(),
            ]
        ]);
    }

    /**
     * Update default template
     */
    public function updateDefaultTemplate(Request $request, $id)
    {
        $request->validate([
            'is_default' => 'required|boolean',
        ]);

        $template = UserInspectionTemplate::query()
            ->where('user_id', Auth::id())
            ->find($id);

        if (!$template) {

            return response()->json([
                'success' => false,
                'message' => 'Template tidak ditemukan',
            ], 404);
        }

        // 🔥 jika set true
        if ($request->boolean('is_default')) {

            // reset default lain
            UserInspectionTemplate::query()
                ->where('user_id', Auth::id())
                ->where('template_type', $template->template_type)
                ->update([
                    'is_default' => false
                ]);

            $template->update([
                'is_default' => true
            ]);

        } else {

            // 🔥 boleh semua false
            $template->update([
                'is_default' => false
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Default template berhasil diperbarui',
            'data' => [
                'id'             => $template->id,
                'template_id'    => $template->template_id,
                'template_type'  => $template->template_type,
                'name'           => $template->name,
                'is_default'     => $template->fresh()->is_default,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Get inspection templates
     */
    private function getInspectionTemplates(): array
    {
        $templates = UserInspectionTemplate::query()
            ->where('user_id', 2)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->groupBy('template_type');

        return [
            'form' => $this->formatTemplates(
                $templates->get(UserInspectionTemplate::TYPE_FORM)
            ),

            'report' => $this->formatTemplates(
                $templates->get(UserInspectionTemplate::TYPE_REPORT)
            ),
        ];
    }

    /**
     * Format template collection
     */
    private function formatTemplates($items): array
    {
        if (!$items) {
            return [];
        }

        return $items->map(function ($item) {

            return [
                'id'             => $item->id,
                'template_id'    => $item->template_id,
                'name'           => $item->name,
                'template_type'  => $item->template_type,
                'is_default'     => $item->is_default,
                'is_active'      => $item->is_active,
            ];

        })->values()->toArray();
    }
}