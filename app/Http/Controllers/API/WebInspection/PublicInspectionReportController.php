<?php

namespace App\Http\Controllers\Api\WebInspection;

use Carbon\Carbon;
use App\Models\InspectionReportLink;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Services\InspectionApiService;
use App\Services\InspectionReportApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PublicInspectionReportController extends Controller
{
    public function __construct(
        protected InspectionApiService $inspectionApi,
        protected InspectionReportApiService $inspectionReportApi
    ) {
        //
    }

    /**
     * Detail report public
     */
    public function show(string $code)
    {
        $reportLink = InspectionReportLink::with([
            'inspection.customer'
        ])
        ->where('code', $code)
        ->where('is_active', true)
        ->first();

        /*
        |--------------------------------------------------------------------------
        | Link not found
        |--------------------------------------------------------------------------
        */

        if (!$reportLink) {

            return response()->json([
                'success' => false,
                'message' => 'Link report tidak ditemukan'
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Expired
        |--------------------------------------------------------------------------
        */

        if (Carbon::now()->greaterThan($reportLink->expired_at)) {

            $reportLink->update([
                'is_active' => false
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Link report sudah kadaluarsa'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Device Lock
        |--------------------------------------------------------------------------
        */

        $deviceHash = sha1(
            request()->userAgent() .
            request()->ip()
        );

        // pertama buka
        if (!$reportLink->device_hash) {

            $reportLink->update([
                'device_hash' => $deviceHash,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'first_opened_at' => now(),
            ]);
        }

        // device berbeda
        if (
            $reportLink->device_hash &&
            $reportLink->device_hash !== $deviceHash
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Link hanya dapat digunakan pada device pertama'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Access Tracking
        |--------------------------------------------------------------------------
        */

        $reportLink->increment('access_count');

        $reportLink->update([
            'last_accessed_at' => now()
        ]);

        $inspection = $reportLink->inspection;
        
        /*
        |--------------------------------------------------------------------------
        | Ambil data vehicle dari API
        |--------------------------------------------------------------------------
        */

        $vehicleName = null;

        try {
            $result = $this->inspectionApi->getInspectionDetail($inspection->inspection_id);
            
            if ($result['success'] === true) {
                $inspectionData = $result['data'];
                $vehicleName = $inspectionData['vehicle']['vehicle_name'] ?? null;
                $display = $inspectionData['vehicle']['display_image']['image_url'] ?? null;
            }
        } catch (\Exception $e) {
            // Log error jika diperlukan
        }

        return response()->json([
            'success' => true,

            'data' => [

                'customer' =>
                    $inspection->customer?->name,

                'vehicle' =>
                    $vehicleName,

                'status' =>
                    $inspection->status_label,
    
                'display_image' =>
                    $display,

                'expired_at' =>
                    Carbon::parse(
                        $reportLink->expired_at
                    )->translatedFormat('d F Y H:i'),

                'is_downloadable' => true
            ]
        ]);
    }

    /**
     * Download PDF dari Backend A
     */
    public function download(string $code)
    {
        $reportLink = InspectionReportLink::with([
            'inspection'
        ])
        ->where('code', $code)
        ->where('is_active', true)
        ->first();

        if (!$reportLink) {

            abort(404, 'Link laporan tidak ditemukan.');
        }

        /*
        |--------------------------------------------------------------------------
        | Expired Check
        |--------------------------------------------------------------------------
        */

        if (now()->greaterThan($reportLink->expired_at)) {

            $reportLink->update([
                'is_active' => false
            ]);

            abort(403, 'Link laporan sudah kadaluarsa.');
        }

        /*
        |--------------------------------------------------------------------------
        | Device Validation
        |--------------------------------------------------------------------------
        */

        // $deviceHash = sha1(
        //     request()->userAgent() .
        //     request()->ip()
        // );

        // // pertama kali buka
        // if (!$reportLink->device_hash) {

        //     $reportLink->update([
        //         'device_hash' => $deviceHash,
        //         'ip_address' => request()->ip(),
        //         'user_agent' => request()->userAgent(),
        //         'first_opened_at' => now(),
        //     ]);
        // }

        // // device berbeda
        // if ($reportLink->device_hash !== $deviceHash) {

        //     abort(403, 'Link hanya dapat digunakan pada device pertama.');
        // }

        /*
        |--------------------------------------------------------------------------
        | Tracking Access
        |--------------------------------------------------------------------------
        */

        $reportLink->increment('access_count');

        $reportLink->update([
            'last_accessed_at' => now(),
        ]);


        /*
        |--------------------------------------------------------------------------
        | Ambil data vehicle dari API
        |--------------------------------------------------------------------------
        */

        $vehicleName = null;

        try {
            $inspection = Inspection::find($reportLink->inspection_id);
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
}