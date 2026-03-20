<?php

namespace App\Http\Controllers\Api\AppInspection;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Services\InspectionReportApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InspectionReportController extends Controller
{
    protected InspectionReportApiService $inspectionReportApi;

    protected array $allowedStatusForView = [
        'under_review',
        'approved',
        'completed',
    ];

    protected array $allowedStatusForGenerate = [
        'under_review',
    ];

    public function __construct(InspectionReportApiService $inspectionReportApi)
    {
        $this->inspectionReportApi = $inspectionReportApi;
    }

    /**
     * Get data report
     */
    public function getDataReport($id)
    {
        $inspection = Inspection::find($id);

        if (!$inspection) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection not found',
            ], 404);
        }

        if ((string) $inspection->inspector_id !== (string) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke laporan ini.',
            ], 403);
        }

        if (!in_array($inspection->status, $this->allowedStatusForView)) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak tersedia untuk status inspeksi saat ini.',
            ], 403);
        }

        try {
            $result = $this->inspectionReportApi->getDataReport($inspection->inspection_id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data laporan. Silakan coba beberapa saat lagi.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data'    => $result['data'],
            ]);

        } catch (\Exception $e) {
            // Detail error hanya dicatat di log server, TIDAK dikirim ke frontend
            // Log::error('InspectionReport getDataReport failed', [
            //     'inspection_id' => $inspection->inspection_id,
            //     'error'         => $e->getMessage(),
            // ]);

            return response()->json([
                'success' => false,
                'message' => 'Layanan laporan sedang tidak tersedia. Silakan coba beberapa saat lagi.',
            ], 503);
        }
    }

    /**
     * Generate PDF
     */
    public function GeneratePDF($id)
    {
        $inspection = Inspection::find($id);

        if (!$inspection) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection not found',
            ], 404);
        }

        if ((string) $inspection->inspector_id !== (string) Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk generate PDF ini.',
            ], 403);
        }

        if (!in_array($inspection->status, $this->allowedStatusForGenerate)) {
            return response()->json([
                'success' => false,
                'message' => 'Generate PDF hanya dapat dilakukan saat status inspeksi adalah "Dalam Review".',
            ], 403);
        }

        try {
            $result = $this->inspectionReportApi->postGeneratePDF($inspection->inspection_id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat PDF. Silakan coba beberapa saat lagi.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data'    => $result['data'],
            ]);

        } catch (\Exception $e) {
            // Detail error hanya dicatat di log server, TIDAK dikirim ke frontend
            // Log::error('InspectionReport GeneratePDF failed', [
            //     'inspection_id' => $inspection->inspection_id,
            //     'error'         => $e->getMessage(),
            // ]);

            return response()->json([
                'success' => false,
                'message' => 'Layanan generate PDF sedang tidak tersedia. Silakan coba beberapa saat lagi.',
            ], 503);
        }
    }
}