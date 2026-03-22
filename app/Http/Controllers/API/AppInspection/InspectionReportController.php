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

    /* =====================
     * HELPER
     * ===================== */

    /**
     * Ambil Inspection dari DB management berdasarkan id lokal,
     * lalu kembalikan inspection_id milik backend inspection.
     */
    private function resolveInspectionId(int $localId): Inspection
    {
        return Inspection::findOrFail($localId);
    }

    /* =====================
     * STORE
     * ===================== */

    /**
     * POST /api/estimasi/{id}
     * $id = id inspection di DB management
     */
    public function store(Request $request, int $id)
    {
        $inspection = $this->resolveInspectionId($id);

        $validated = $request->validate([
            'part_name'          => 'required|string|max:255',
            'repair_description' => 'required|string',
            'urgency'            => 'required|string|in:immediate,soon,optional,monitor',
            'status'             => 'required|string|in:required,recommended,optional',
            'estimated_cost'     => 'required|numeric|min:0',

            // Optional
            'notes'                              => 'nullable|string',
            'related_sources'                    => 'nullable|array',
            'related_sources.damages'            => 'nullable|array',
            'related_sources.damages.*'          => 'integer',
            'related_sources.inspection_items'   => 'nullable|array',
            'related_sources.inspection_items.*' => 'integer',
        ]);

        // Ambil nama user yang sedang login
        $createdByName = Auth::user()->name;

        $payload = array_merge($validated, [
            'created_by' => $createdByName,
        ]);

        // Teruskan ke backend inspection menggunakan inspection_id relasi
        $result = $this->inspectionReportApi->storeEstimasi(
            $inspection->inspection_id,
            $payload
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error'   => $result['error'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Estimasi berhasil dibuat.',
            'data'    => $result['data'],
        ], 201);
    }

    /* =====================
     * UPDATE
     * ===================== */

    /**
     * PUT /api/estimasi/{id}/{estimasiId}
     * $id        = id inspection di DB management
     * $estimasiId = id estimasi di backend inspection
     */
    public function update(Request $request, int $id, int $estimasiId)
    {
        $inspection = $this->resolveInspectionId($id);

        $validated = $request->validate([
            'part_name'          => 'sometimes|required|string|max:255',
            'repair_description' => 'sometimes|required|string',
            'urgency'            => 'sometimes|required|string|in:immediate,soon,optional,monitor',
            'status'             => 'sometimes|required|string|in:required,recommended,optional',
            'estimated_cost'     => 'sometimes|required|numeric|min:0',

            // Optional
            'notes'                              => 'nullable|string',
            'related_sources'                    => 'nullable|array',
            'related_sources.damages'            => 'nullable|array',
            'related_sources.damages.*'          => 'integer',
            'related_sources.inspection_items'   => 'nullable|array',
            'related_sources.inspection_items.*' => 'integer',
        ]);

        // Ambil nama user yang sedang login sebagai updated_by
        $updatedByName = Auth::user()->name;

        $payload = array_merge($validated, [
            'updated_by' => $updatedByName,
        ]);

        $result = $this->inspectionReportApi->updateEstimasi(
            $inspection->inspection_id,
            $estimasiId,
            $payload
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error'   => $result['error'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Estimasi berhasil diupdate.',
            'data'    => $result['data'],
        ]);
    }

    /* =====================
     * DESTROY
     * ===================== */

    /**
     * DELETE /api/estimasi/{id}/{estimasiId}
     * $id        = id inspection di DB management
     * $estimasiId = id estimasi di backend inspection
     */
    public function destroy(int $id, int $estimasiId)
    {
        $inspection = $this->resolveInspectionId($id);

        $result = $this->inspectionReportApi->destroyEstimasi(
            $inspection->inspection_id,
            $estimasiId
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error'   => $result['error'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Estimasi berhasil dihapus.',
        ]);
    }

}