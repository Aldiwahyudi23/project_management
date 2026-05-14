<?php

namespace App\Http\Controllers\Api\AppInspection;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Models\MasterData\Customer\Customer;
use App\Models\MasterData\UserInspectionTemplate;
use App\Services\InspectionApiService;
use App\Services\InspectionTemplateApiService;
use App\Services\InspectionVehicleApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InspectionController extends Controller
{
    public function __construct(
    protected InspectionApiService $inspectionApi,
    protected InspectionTemplateApiService $apiService,
    protected InspectionVehicleApiService $inspectionService
    ) {
        //
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created inspection.
     * 
     * 1. Resolve customer_id (dari request atau buat baru)
     * 2. Kirim data ke Inspection Backend untuk dapatkan inspection_id
     * 3. Simpan ke Management Backend dengan inspection_id tersebut
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // --- Customer: salah satu dari dua skenario ---
            'customer_id'       => 'nullable|exists:customers,id',
            // Wajib jika customer_id tidak ada
            'customer_name'     => 'required_without:customer_id|string|max:255',
            'customer_phone'    => 'required_without:customer_id|string|max:20',
            'customer_address'  => 'nullable|string|max:500',

            // toggle
            'is_scheduled'      => 'required|boolean',
            'inspection_date'   => 'nullable|date',

            'reference'         => 'nullable|string|max:255',
            'settings'          => 'nullable|array',

            // vehicle
            'license_plate'     => 'required|string|max:20',
            'vehicle_name'      => 'required|string|max:255',
            'vehicle_id'        => 'required|integer',
            'template_id'       => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {

            // 🔹 Ambil user login
            $user = Auth::user();

            // 🔒 Belum login
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // 🔹 Validasi role inspector
            if (!$user->hasRole('inspector')) {

                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya inspector yang dapat mengakses data ini'
                ], 403);
            }

            // 🔹 Gunakan sebagai inspector_id
            $inspectorId = $user->id;

            /*
            |--------------------------------------------------------------------------
            | VALIDASI ACTIVE INSPECTION
            |--------------------------------------------------------------------------
            | Hanya berlaku jika bukan schedule
            |--------------------------------------------------------------------------
            */
            if (!$request->is_scheduled) {

                $hasRunningInspection = Inspection::query()
                    ->where('inspector_id', $inspectorId)
                    ->whereIn('status', [
                        'in_progress',
                        'revision',
                    ])
                    ->exists();

                if ($hasRunningInspection) {

                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Inspector masih memiliki inspeksi yang sedang berjalan. Selesaikan atau tunda inspeksi sebelumnya terlebih dahulu. Atau buat inspeksi dengan jadwal (scheduled) untuk menghindari aturan ini.'
                    ], 400);
                }
            }

            // ========================================
            // 🔥 STEP 1: RESOLVE CUSTOMER ID
            // ========================================
            if ($request->filled('customer_id')) {

                // Pakai customer yang sudah ada
                $customerId = $request->customer_id;
                $customer   = Customer::findOrFail($customerId);

            } else {

                // Buat customer baru
                $customer = Customer::create([
                    'name'    => $request->customer_name,
                    'phone'   => $request->customer_phone,
                    'address' => $request->customer_address ?? null,
                ]);

                $customerId = $customer->id;
            }

            // ========================================
            // 🔥 STEP 2: LOGIC TOGGLE SCHEDULED
            // ========================================
            if ($request->is_scheduled) {

                if (!$request->inspection_date) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Tanggal wajib diisi jika dijadwalkan'
                    ], 422);
                }

                $inspectionDate = Carbon::parse($request->inspection_date, 'Asia/Jakarta');
                $now = Carbon::now('Asia/Jakarta');

                  // 🔥 1. Tidak boleh masa lalu
                if ($inspectionDate->lessThanOrEqualTo($now)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Jadwal inspeksi tidak boleh kurang dari waktu sekarang'
                    ], 422);
                }

                // 🔥 2. Validasi minimal +30 menit
                if (!$inspectionDate->gt($now->copy()->addMinutes(30))) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Jadwal inspeksi minimal 30 menit dari waktu sekarang'
                    ], 422);
                }

                // 🔥 3. Validasi jam operasional (07:00 - 19:00)
                $hour = $inspectionDate->hour;

                if ($hour < 7 || $hour > 19) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Jadwal inspeksi hanya tersedia pukul 07:00 - 19:00'
                    ], 422);
                }

                $status          = 'accepted';
                $status_external = 'draft';

            } else {

                $status          = 'in_progress';
                $status_external = 'in_progress';
                $inspectionDate  = now();
            }

            // ========================================
            // 🔥 GET DEFAULT REPORT TEMPLATE
            // ========================================
            $defaultReportTemplate = UserInspectionTemplate::query()
                ->where('user_id', $user->id)
                ->where('template_type', 'report')
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            // jika tidak ada → null
            $reportTemplateId = $defaultReportTemplate?->template_id;

            // ========================================
            // 🔥 STEP 3: PREPARE & HIT INSPECTION BACKEND
            // ========================================
            $inspectionBackendData = [
                'license_plate'      => $request->license_plate,
                'vehicle_name'       => $request->vehicle_name,
                'vehicle_id'         => $request->vehicle_id,

                // form template
                'template_id'        => $request->template_id,

                // report template default inspector
                'report_template_id' => $reportTemplateId,

                'inspection_date'    => $inspectionDate,
                'status'             => $status_external,
            ];

            $result = $this->inspectionApi->postStoreInspection($inspectionBackendData);
            if (!$result['success']) {
                DB::rollBack();

                // 🔥 Ambil pesan terdalam (prioritas dari backend inspection)
                $message = $result['error']['message']
                    ?? $result['message']
                    ?? 'Terjadi kesalahan saat membuat inspeksi';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $result['status_code'] ?? 422);
            }

            if (!isset($result['inspection_id']) || empty($result['inspection_id'])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid response from inspection backend',
                    'debug'   => $result
                ], 500);
            }

            // ========================================
            // 🔥 STEP 4: SIMPAN KE MANAGEMENT
            // ========================================
            $inspection = Inspection::create([
                'uuid'            => \Illuminate\Support\Str::uuid(),
                'inspection_id'   => $result['inspection_id'],
                'customer_id'     => $customerId,           // ✅ hasil resolve di atas
                'inspector_id'    => $inspectorId,             // ✅ pakai user yang login (bukan dari request)
                'submitted_by'    => Auth::id(),          // ✅ pakai user yang login (bukan dari request) 
                'status'          => $status,
                'inspection_date' => $inspectionDate,
                'reference'       => $request->reference,
                'settings'        => $request->settings,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inspection created successfully',
                'data' => [
                    'id'              => $inspection->id,
                    'uuid'            => $inspection->uuid,
                    'inspection_id'   => $inspection->inspection_id,
                    'inspection_code' => $result['inspection_code'],
                    'customer'        => $customer,          // ✅ return full customer object
                    'inspector'       => $inspection->inspector,
                    'status'          => $inspection->status,
                    'status_label'    => $inspection->status_label,
                    'inspection_date' => $inspection->inspection_date,
                    'reference'       => $inspection->reference,
                    'settings'        => $inspection->settings,
                    'external_data'   => $result['data'],
                    'created_at'      => $inspection->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create inspection',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Get template for user (FORM type only)
     */
    public function getFormTemplates(Request $request)
    {
        $userId = 2;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // =========================
        // GET GLOBAL TEMPLATE
        // =========================
        $globalTemplates = collect(
            $this->apiService->getDefaultTemplates()['data'] ?? []
        )->map(function ($item) {
            return [
                'id'          => $item['id'],
                'name'        => $item['name'],
                'description' => $item['description'],
                'is_default'  => $item['is_default'],
                'source'      => 'global',
            ];
        });

        // =========================
        // GET USER TEMPLATE
        // =========================
        $userTemplates = UserInspectionTemplate::query()
            ->byUser($userId)
            ->byType(UserInspectionTemplate::TYPE_FORM)
            ->where('is_active', true)
            ->get();

        // =========================
        // USER TIDAK PUNYA TEMPLATE
        // =========================
        if ($userTemplates->isEmpty()) {

            return response()->json([
                'source' => 'global_default',
                'data'   => $globalTemplates->values(),
            ]);
        }

        // =========================
        // FORMAT USER TEMPLATE
        // =========================
        $formattedUserTemplates = $userTemplates->map(function ($item) {
            return [
                'id'          => $item->template_id,
                'name'        => $item->name,
                'description' => null,
                'is_default'  => $item->is_default,
                'source'      => 'user',
            ];
        });

        // =========================
        // CEK USER DEFAULT
        // =========================
        $defaultTemplate = $formattedUserTemplates
            ->firstWhere('is_default', true);

        // =========================
        // ADA DEFAULT
        // =========================
        if ($defaultTemplate) {

            return response()->json([
                'source' => 'user_default',
                'data'   => $defaultTemplate,
            ]);
        }

        // =========================
        // TIDAK ADA DEFAULT
        // USER ACTIVE + GLOBAL
        // =========================
        $mergedTemplates = $formattedUserTemplates
            ->concat($globalTemplates)
            ->values();

        return response()->json([
            'source' => 'mixed_selection',
            'data'   => $mergedTemplates,
        ]);
    }

    
    /** 
      * GET /api/inspection/vehicle/by-plate?license_plate=XXX
     */
    public function getByPlate(Request $request)
    {
        $plate = $request->query('license_plate');

        if (!$plate) {
            return response()->json([
                'success' => false,
                'message' => 'license_plate is required',
            ], 422);
        }

        // Panggil service
        $result = $this->inspectionService->getVehicleByPlate($plate);

        // RETURN TANPA UBAH STRUKTUR
        return response()->json($result);
    }


    /**
     * Update inspection form template
     */
    public function updateInspectionTemplate(Request $request, $id)
    {
        $request->validate([
            'template_id' => 'required|integer',
        ]);

        // Ambil inspection lokal Backend B
        $inspection = Inspection::find($id);

        if (!$inspection) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection tidak ditemukan'
            ], 404);
        }

        // 🔒 hanya inspector yang assigned yang boleh update
        if ((int) $inspection->inspector_id !== (int) Auth::id()) {

            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengubah template inspeksi ini'
            ], 403);
        }

        // 🔒 pastikan punya inspection_id Backend A
        if (!$inspection->inspection_id) {

            return response()->json([
                'success' => false,
                'message' => 'Inspection backend reference tidak ditemukan'
            ], 422);
        }

        // Kirim ke Backend A
        $result = $this->inspectionApi->updateInspectionTemplate(
            $inspection->inspection_id,
            [
                'template_id' => $request->template_id
            ]
        );

        return response()->json(
            $result,
            $result['status_code'] ?? 200
        );
    }

    /**
     * Update report template
     */
    public function updateInspectionReportTemplate(Request $request, $id)
    {
        $request->validate([
            'report_template_id' => 'nullable|integer',
        ]);

        // Ambil inspection lokal Backend B
        $inspection = Inspection::find($id);

        if (!$inspection) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection tidak ditemukan'
            ], 404);
        }

        // 🔒 hanya inspector assigned yang boleh update
        if ((int) $inspection->inspector_id !== (int) Auth::id()) {

            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengubah report template inspeksi ini'
            ], 403);
        }

        // 🔒 pastikan punya inspection_id Backend A
        if (!$inspection->inspection_id) {

            return response()->json([
                'success' => false,
                'message' => 'Inspection backend reference tidak ditemukan'
            ], 422);
        }

        // Kirim ke Backend A
        $result = $this->inspectionApi->updateInspectionReportTemplate(
            $inspection->inspection_id,
            [
                'report_template_id' => $request->report_template_id
            ]
        );

        return response()->json(
            $result,
            $result['status_code'] ?? 200
        );
    }

    
}
