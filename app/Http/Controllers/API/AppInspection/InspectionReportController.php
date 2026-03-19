<?php

namespace App\Http\Controllers\Api\AppInspection;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Services\InspectionReportApiService;
use Illuminate\Http\Request;

class InspectionReportController extends Controller
{
    protected InspectionReportApiService $inspectionReportApi;

    public function __construct(InspectionReportApiService $inspectionReportApi)
    {
        $this->inspectionReportApi = $inspectionReportApi;
    }

    /**
     * Get brands for dropdown
     */
    public function getDataReport($id)
    {
             // Cari inspection berdasarkan ID
        $inspection = Inspection::find($id);
        
        if (!$inspection) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection not found'
            ], 404);
        }

        // Panggil API eksternal dengan inspection_id
        $result = $this->inspectionReportApi->getDataReport($inspection->inspection_id);
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                 'message' => $result['message'] ?? 'Failed to generate PDF'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

    public function GeneratePDF($id)
    {
        // Cari inspection berdasarkan ID
        $inspection = Inspection::find($id);
        
        if (!$inspection) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection not found'
            ], 404);
        }

        // Panggil API eksternal untuk generate PDF
        $result = $this->inspectionReportApi->postGeneratePDF($inspection->inspection_id);
        
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to generate PDF'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data']
        ]);
    }

}