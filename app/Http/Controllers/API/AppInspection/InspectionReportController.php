<?php

namespace App\Http\Controllers\Api\AppInspection;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Models\InspectionReportLink;
use App\Services\InspectionApiService;
use App\Services\InspectionReportApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InspectionReportController extends Controller
{
    protected InspectionReportApiService $inspectionReportApi;
    protected InspectionApiService $inspectionApi;

    protected array $allowedStatusForDownload = [
        'approved',
        'completed',
    ];
    protected array $allowedStatusForView = [
        'under_review',
        'approved',
        'completed',
    ];

    protected array $allowedStatusForGenerate = [
        'under_review',
    ];

    public function __construct(InspectionReportApiService $inspectionReportApi, InspectionApiService $inspectionApi)
    {
        $this->inspectionReportApi = $inspectionReportApi;
        $this->inspectionApi = $inspectionApi;
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

            // Update inspection:ubah status jika perlu
            $inspection->update([
                'status'        => 'approved',
            ]);

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

    public function downloadPDF($id)
    {
        // if (!auth('sanctum')->check()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized'
        //     ], 401);
        // }

        $inspection = Inspection::find($id);

        if (!$inspection) {
            return response()->json(['success' => false, 'message' => 'Inspection not found'], 404);
        }

                /*
        |--------------------------------------------------------------------------
        | Ambil data vehicle dari API
        |--------------------------------------------------------------------------
        */

        $vehicleName = null;

        try {
            $inspectionId = $inspection->inspection_id;
            $result = $this->inspectionApi->getInspectionDetail($inspection->inspection_id);
            
            if ($result['success'] === true) {
                $inspectionData = $result['data'];
                $plateNumber = $inspectionData['vehicle']['license_plate'] ?? null;
            }
        } catch (\Exception $e) {
            // Log error jika diperlukan
        }

        $fileName = str_replace(' ', '-', strtolower($plateNumber));

        /*
        |--------------------------------------------------------------------------
        | Return Stream Download
        |--------------------------------------------------------------------------
        */

        $result = $this->inspectionReportApi->getLinkPdf($inspectionId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan dokumen.',
            ], 500);
        }

        $url = $result['data']['url'];

        // Fetch PDF dari Backend A via signed URL
        $pdf = Http::timeout(30)->get($url);

        if (!$pdf->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil file.',
            ], 500);
        }

        return response($pdf->body(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laporan-inspeksi-' . $fileName . '.pdf"',
        ]);
    }

    public function previewPDF($id)
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $inspection = Inspection::find($id);

        if (!$inspection) {
            return response()->json(['success' => false, 'message' => 'Inspection not found'], 404);
        }

        try {
            // Sama seperti download — ambil signed URL dari Backend A
            $result = $this->inspectionReportApi->getLinkPdf($inspection->inspection_id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Gagal mendapatkan link dokumen.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $result['data']['url']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('preview failed', [
                'inspection_id' => $inspection->inspection_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Layanan sedang tidak tersedia.'
            ], 503);
        }
    }

//=============================Untuk keperluan kirim report via chat =======================
    /**
     * GET DATA WHATSAPP
     */
    public function sendWhatsapp($id)
    {
        $inspection = Inspection::with([
            'customer',
            'seller',
            'inspector',
            'submittedBy',
            'reportLink',
            'externalInspection'
        ])->findOrFail($id);

        $customer = $inspection->customer;

        $phone = $this->normalizePhoneNumber(
            $customer?->phone
        );

        $message = '';

        $reportLink = null;

        /*
        |--------------------------------------------------------------------------
        | UNDER REVIEW
        |--------------------------------------------------------------------------
        */
        if ($inspection->status === 'under_review') {

            $message = $this->generateReportChatMessage(
                $inspection
            );
        }

        /*
        |--------------------------------------------------------------------------
        | APPROVED / COMPLETED
        |--------------------------------------------------------------------------
        */
        if (
            in_array(
                $inspection->status,
                ['approved', 'completed']
            )
        ) {

            $report = $this->generateReportLink(
                $inspection
            );

            $reportLink =
                'https://cekmobil.online/report-inspection/' .
                $report->code;

            $message = $this->generateFileReportMessage(
                inspection: $inspection,
                reportLink: $reportLink
            );
        }

        return response()->json([

            'success' => true,

            'data' => [

                'inspection_id' => $inspection->id,

                'status' => $inspection->status,

                'customer' => [
                    'name'  => $customer?->name,
                    'phone' => $phone,
                ],

                'vehicle' => [
                    'license_plate' =>
                        $inspection->externalInspection?->license_plate,
                ],

                'report_link' => $reportLink,

                'message' => $message,

                'whatsapp_url' => $phone
                    ? 'https://wa.me/' .
                        $phone .
                        '?text=' .
                        urlencode($message)
                    : null,
            ]
        ]);
    }

    /**
     * GENERATE REPORT LINK
     */
    protected function generateReportLink(
        Inspection $inspection
    ) {

        $existing = InspectionReportLink::where(
                'inspection_id',
                $inspection->id
            )
            ->where('is_active', true)
            ->latest()
            ->first();

        /*
        |--------------------------------------------------------------------------
        | CEK LINK LAMA
        |--------------------------------------------------------------------------
        */

        if ($existing) {

            $remainingHours =
                now()->diffInHours(
                    $existing->expired_at,
                    false
                );

            /*
            |--------------------------------------------------------------------------
            | MASIH VALID & SISA > 12 JAM
            |--------------------------------------------------------------------------
            */

            if (
                $remainingHours > 12 &&
                $existing->expired_at->isFuture()
            ) {
                return $existing;
            }

            /*
            |--------------------------------------------------------------------------
            | EXPIRED / HAMPIR EXPIRED
            |--------------------------------------------------------------------------
            */

            $existing->update([
                'is_active' => false
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | GENERATE BARU
        |--------------------------------------------------------------------------
        */

        return InspectionReportLink::create([

            'inspection_id' => $inspection->id,

            'token' => Str::random(64),

            'code' => $this->generateUniqueCode(),

            // contoh expired 1 hari
            'expired_at' => now()->addDay(),

            'is_active' => true,
        ]);
    }

    /**
     * MESSAGE:
     * UNDER REVIEW
     */
    protected function generateReportChatMessage(
        Inspection $inspection
    ): string {

        $customerName =
            $inspection->customer?->name ?? 'Pelanggan';

        $plate =
            $inspection->externalInspection?->license_plate ?? '-';

        return
"Hallo {$customerName},

Laporan inspeksi kendaraan dengan nomor polisi {$plate} saat ini sedang dalam proses review oleh tim kami.

Hasil laporan final akan segera dikirimkan setelah proses pengecekan selesai.

Terima kasih sudah menggunakan layanan inspeksi kami.";
    }

    /**
     * MESSAGE:
     * FILE REPORT
     */
    protected function generateFileReportMessage(
        Inspection $inspection,
        string $reportLink
    ): string {

        $customerName =
            $inspection->customer?->name ?? 'Pelanggan';

        $plate =
            $inspection->externalInspection?->license_plate ?? '-';

        return
"Hallo {$customerName},

Hasil inspeksi kendaraan dengan nomor polisi {$plate} telah selesai.

Silakan buka laporan inspeksi melalui link berikut:

{$reportLink}

Link memiliki batas waktu akses demi keamanan data laporan.

Terima kasih.";
    }

    /**
     * NORMALIZE PHONE
     * RESULT:
     * 628xxxx
     */
    protected function normalizePhoneNumber(
        ?string $phone
    ): ?string {

        if (!$phone) {
            return null;
        }

        // hapus selain angka
        $phone = preg_replace(
            '/[^0-9]/',
            '',
            $phone
        );

        // 08xxx => 628xxx
        if (Str::startsWith($phone, '0')) {

            $phone =
                '62' . substr($phone, 1);
        }

        // 8xxx => 628xxx
        if (Str::startsWith($phone, '8')) {

            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * GENERATE UNIQUE CODE
     */
    protected function generateUniqueCode(): string
    {
        do {

            $code =
                'RPT-' .
                strtoupper(Str::random(4)) .
                '-' .
                random_int(1000, 9999);

        } while (
            InspectionReportLink::where(
                'code',
                $code
            )->exists()
        );

        return $code;
    }

}