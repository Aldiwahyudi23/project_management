<?php

namespace App\Http\Controllers\Api\AppInspection\Job;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Inspection\JobListResource;
use App\Http\Resources\Api\Inspection\JobDetailResource;
use App\Models\Inspection;
use App\Services\InspectionApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobController extends Controller
{
    public function __construct(
        protected InspectionApiService $inspectionApi
    ) {
        //
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS GROUPING
    |--------------------------------------------------------------------------
    */

    private const STATUS_DRAFT = ['draft'];

    private const STATUS_COMPLETED = [
        'approved',
        'rejected',
        'completed',
        'cancelled'
    ];

    /*
    |--------------------------------------------------------------------------
    | FLOW CONFIG (sementara manual)
    |--------------------------------------------------------------------------
    */

    private function isTravelFlowEnabled(): bool
    {
        // 🔥 sementara manual
        return false; 
        // ganti false untuk test tanpa travel flow
    }

    private function getProcessStatuses(): array
    {
        if ($this->isTravelFlowEnabled()) {
            return [
                'accepted',
                'pending',
                'on_the_way',
                'arrived',
                'in_progress',
                'paused',
                'under_review',
                'revision'
            ];
        }

        return [
            'accepted',
            'in_progress',
            'paused',
            'under_review',
            'revision'
        ];
    }

    private function getAllUpdatableStatuses(): array
    {
        return array_merge(
            $this->getProcessStatuses(),
            self::STATUS_COMPLETED
        );
    }

        /*
    |--------------------------------------------------------------------------
    | ACTIVE LOCK (HANYA 1 JOB AKTIF)
    |--------------------------------------------------------------------------
    */

    private function getActiveStatuses(): array
    {
        if ($this->isTravelFlowEnabled()) {
            return [
                'on_the_way',
                'arrived',
                'in_progress',
                'paused'
            ];
        }

        return [
            'in_progress',
            'paused'
        ];
    }
    private function hasActiveJob($inspectorId, $excludeId = null): bool
    {
        $query = Inspection::where('inspector_id', $inspectorId)
            ->whereIn('status', $this->getActiveStatuses());

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
    /*
    |--------------------------------------------------------------------------
    | STATE MACHINE (ANTI LONCAT)
    |--------------------------------------------------------------------------
    */

    private function getStatusFlow(): array
    {
        if ($this->isTravelFlowEnabled()) {
            return [
                'draft' => ['accepted','cancelled'],
                'accepted' => ['on_the_way', 'cancelled'],
                'on_the_way' => ['arrived', 'pending', 'cancelled'],
                'arrived' => ['in_progress', 'pending','cancelled'],
                'pending' => ['on_the_way', 'cancelled'], 
                'in_progress' => ['in_progress', 'rejected'],
                'paused' => ['in_progress','rejected'],
                'revision' => ['in_progress'],
            ];
        }

        return [
            'draft' => ['accepted','cancelled'],
            'accepted' => ['in_progress', 'cancelled'],
            'in_progress' => ['in_progress', 'rejected'],
            'paused' => ['in_progress','rejected'],
            'revision' => ['in_progress'],
        ];
    }


    private function canTransition(string $current, string $next): bool
    {
        $flow = $this->getStatusFlow();

        return isset($flow[$current]) &&
               in_array($next, $flow[$current]);
    }

    /*
    |--------------------------------------------------------------------------
    | JOB COUNT
    |--------------------------------------------------------------------------
    */

    public function jobCount()
    {
        $user = Auth::user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'draft' => Inspection::where('inspector_id', $user->id)
                    ->whereIn('status', self::STATUS_DRAFT)
                    ->count(),

                'process' => Inspection::where('inspector_id', $user->id)
                    ->whereIn('status', $this->getProcessStatuses())
                    ->count(),
            ]
        ]);
    }

    /**
     * GET /api/app-inspection/jobs/draft
     * Mendapatkan list inspeksi dengan status draft
     */
    public function getDraftJobs(Request $request)
    {
        try {
            $user = Auth::user();

            $query = Inspection::
            // where('inspector_id', $user->id)
            //     ->
                whereIn('status', self::STATUS_DRAFT);

            /*
            |--------------------------------------------------------------------------
            | 🔍 SEARCH (vehicle_name & license_plate dari settings JSON)
            |--------------------------------------------------------------------------
            */
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('settings->vehicle_name', 'like', "%{$search}%")
                    ->orWhere('settings->license_plate', 'like', "%{$search}%");
                });
            }

            /*
            |--------------------------------------------------------------------------
            | 🔄 Sorting
            |--------------------------------------------------------------------------
            */
            $sortField = $request->get('sort_field', 'inspection_date');
            $sortOrder = $request->get('sort_order', 'asc');

            $allowedSortFields = ['inspection_date', 'created_at', 'status'];

            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'inspection_date';
            }

            $query->orderBy($sortField, $sortOrder);

            /*
            |--------------------------------------------------------------------------
            | 📄 Pagination
            |--------------------------------------------------------------------------
            */
            $perPage = $request->get('per_page', 10);
            $jobs = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Draft jobs retrieved successfully',
                'data' => JobListResource::collection($jobs),
                'pagination' => [
                    'current_page' => $jobs->currentPage(),
                    'per_page' => $jobs->perPage(),
                    'total' => $jobs->total(),
                    'last_page' => $jobs->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve draft jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/app-inspection/jobs/process
     * Mendapatkan list inspeksi dengan status process
     */
    public function getProcessJobs(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = Inspection::where('inspector_id', $user->id)
                ->whereIn('status', $this->getProcessStatuses());
            
            /*
            |--------------------------------------------------------------------------
            | 🔍 SEARCH (vehicle_name & license_plate dari settings JSON)
            |--------------------------------------------------------------------------
            */
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('settings->vehicle_name', 'like', "%{$search}%")
                    ->orWhere('settings->license_plate', 'like', "%{$search}%");
                });
            }
            
            // Filter by specific status
            if ($request->has('status') && in_array($request->status, $this->getProcessStatuses())) {
                $query->where('status', $request->status);
            }
            
            // Sorting
            $sortField = $request->get('sort_field', 'inspection_date');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortField, $sortOrder);
            
            $perPage = $request->get('per_page', 10);
            $jobs = $query->paginate($perPage);

            
            // Ambil sample inspection untuk mendapatkan status yang tersedia
            $sampleInspection = Inspection::where('inspector_id', $user->id)
                ->whereIn('status', $this->getProcessStatuses())
                ->first();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Process jobs retrieved successfully',
                'data' => JobListResource::collection($jobs),
                'pagination' => [
                    'current_page' => $jobs->currentPage(),
                    'per_page' => $jobs->perPage(),
                    'total' => $jobs->total(),
                    'last_page' => $jobs->lastPage(),
                ],
                'filters' => [
                    'available_statuses' => array_map(function($status) use ($sampleInspection) {
                        // Buat instance inspection sementara untuk mendapatkan label & color
                        $tempInspection = new Inspection(['status' => $status]);
                        
                        return [
                            'value' => $status,
                            'label' => $tempInspection->status_label,
                            'color' => $tempInspection->status_color,
                        ];
                    }, $this->getProcessStatuses())
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve process jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/app-inspection/jobs/completed
     * Mendapatkan list inspeksi dengan status completed
     * (dengan filter tahun, bulan, status, dan search)
     */
    public function getCompletedJobs(Request $request)
    {
        try {
            $user = Auth::user();

            /*
            |--------------------------------------------------------------------------
            | ✅ Validation
            |--------------------------------------------------------------------------
            */
            $validator = Validator::make($request->all(), [
                'year'   => 'nullable|integer|min:2020|max:' . date('Y'),
                'month'  => 'nullable|integer|min:1|max:12',
                'status' => 'nullable|in:' . implode(',', self::STATUS_COMPLETED),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors()
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 🔎 Base Query
            |--------------------------------------------------------------------------
            */
            $query = Inspection::
            // where('inspector_id', $user->id)
            //     ->
                whereIn('status', self::STATUS_COMPLETED);

            /*
            |--------------------------------------------------------------------------
            | 📅 Filter Tahun
            |--------------------------------------------------------------------------
            */
            if ($request->filled('year')) {
                $query->whereYear('inspection_date', $request->year);
            }

            /*
            |--------------------------------------------------------------------------
            | 📆 Filter Bulan
            |--------------------------------------------------------------------------
            */
            if ($request->filled('month')) {
                $query->whereMonth('inspection_date', $request->month);
            }

            /*
            |--------------------------------------------------------------------------
            | 📌 Filter Status Specific
            |--------------------------------------------------------------------------
            */
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            /*
            |--------------------------------------------------------------------------
            | 🔍 Search dari JSON settings
            |--------------------------------------------------------------------------
            */
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('settings->vehicle_name', 'like', "%{$search}%")
                    ->orWhere('settings->license_plate', 'like', "%{$search}%");
                });
            }

            /*
            |--------------------------------------------------------------------------
            | 🔄 Sorting (default terbaru)
            |--------------------------------------------------------------------------
            */
            $sortField = $request->get('sort_field', 'inspection_date');
            $sortOrder = $request->get('sort_order', 'desc');

            $allowedSortFields = ['inspection_date', 'created_at', 'status'];

            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'inspection_date';
            }

            $query->orderBy($sortField, $sortOrder);

            /*
            |--------------------------------------------------------------------------
            | 📄 Pagination
            |--------------------------------------------------------------------------
            */
            $perPage = $request->get('per_page', 10);
            $jobs = $query->paginate($perPage);

            /*
            |--------------------------------------------------------------------------
            | 📊 Statistics
            |--------------------------------------------------------------------------
            */
            $stats = $this->getCompletedJobsStats(
                $user->id,
                $request->year ?? date('Y'),
                $request->month
            );

            /*
            |--------------------------------------------------------------------------
            | 🎛 Filters Info
            |--------------------------------------------------------------------------
            */
            $availableStatuses = array_map(function ($status) {
                $tempInspection = new Inspection(['status' => $status]);

                return [
                    'value' => $status,
                    'label' => $tempInspection->status_label,
                    'color' => $tempInspection->status_color,
                ];
            }, self::STATUS_COMPLETED);

            return response()->json([
                'status'  => 'success',
                'message' => 'Completed jobs retrieved successfully',
                'data'    => JobListResource::collection($jobs),
                'pagination' => [
                    'current_page' => $jobs->currentPage(),
                    'per_page'     => $jobs->perPage(),
                    'total'        => $jobs->total(),
                    'last_page'    => $jobs->lastPage(),
                ],
                'statistics' => $stats,
                'filters' => [
                    'year'               => $request->year,
                    'month'              => $request->month,
                    'available_statuses' => $availableStatuses,
                    'available_months'   => $this->getAvailableMonths($user->id, $request->year),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to retrieve completed jobs',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    /**
     * GET /api/app-inspection/jobs/{id}
     * Mendapatkan detail inspeksi
     */
    public function show($id)
    {
        try {
            $user = Auth::user();

            $inspection = Inspection::with([
                    'customer', 
                    'inspector', 
                    'submittedBy', 
                    'sellers'
                ])
                // ->where('inspector_id', $user->id)
                // ->where('uuid', $id)
                // ->first();
                ->find($id);

            if (!$inspection) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Inspection not found'
                ], 404);
            }

            // 🔥 Hapus panggilan external API kalau tidak perlu
            $externalData = $this->inspectionApi->getInspectionDetail($inspection->inspection_id);
            $inspection->external_api = $externalData;

            // 🔹 Ambil semua transitions
            $availableTransitions = $this->getStatusFlow()[$inspection->status] ?? [];

            // 🔹 Pisahkan menjadi dua kategori
            $cancelActions = array_filter($availableTransitions, function($status) {
                return in_array($status, ['cancelled', 'pending', 'rejected']);
            });

            $nextStepActions = array_filter($availableTransitions, function($status) {
                return !in_array($status, ['cancelled', 'pending', 'rejected']);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Inspection detail retrieved successfully',
                'data' => new JobDetailResource($inspection),
                'available_next_statuses' => [
                    'cancel_actions' => array_values($cancelActions), // tombol 1: Batal / Pending / Rejected
                    'next_step_actions' => array_values($nextStepActions), // tombol 2: status lanjutan
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve inspection detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PATCH /api/app-inspection/jobs/{id}/status
     * Update status inspeksi
     */
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:' . implode(',', $this->getAllUpdatableStatuses()),
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $inspection = Inspection::where('inspector_id', $user->id)
            ->find($id);

        if (!$inspection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Inspection not found'
            ], 404);
        }

        $currentStatus = $inspection->status;
        $newStatus = $request->status;

        // 🔥 VALIDASI FLOW
        if (!$this->canTransition($currentStatus, $newStatus)) {
            return response()->json([
                'status' => 'error',
                'message' => "Tidak dapat mengubah status dari {$currentStatus} ke {$newStatus}"
            ], 400);
        }

        // 🔥 VALIDASI ACTIVE LOCK
        if (in_array($newStatus, $this->getActiveStatuses())) {
            if ($this->hasActiveJob($inspection->inspector_id, $inspection->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Masih ada inspeksi aktif. Silakan pending-kan inspeksi lain terlebih dahulu.'
                ], 400);
            }
        }

        // 🔥 UPDATE
        $inspection->status = $newStatus;

        if ($request->filled('notes')) {
            $inspection->notes = $request->notes;
        }

        $inspection->save();
        $inspection->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Status berhasil diperbarui',
            'data' => [
                'id' => $inspection->id,
                'status' => $inspection->status,
                'status_label' => $inspection->status_label,
                'status_color' => $inspection->status_color,
                'updated_at' => $inspection->updated_at->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Helper: Get statistics for completed jobs
     */
    private function getCompletedJobsStats($inspectorId, $year, $month = null)
    {
        $query = Inspection::where('inspector_id', $inspectorId)
            ->whereIn('status', self::STATUS_COMPLETED)
            ->whereYear('inspection_date', $year);
        
        if ($month) {
            $query->whereMonth('inspection_date', $month);
        }
        
        $total = $query->count();
        
        $statusCounts = [];
        foreach (self::STATUS_COMPLETED as $status) {
            $statusQuery = Inspection::where('inspector_id', $inspectorId)
                ->where('status', $status)
                ->whereYear('inspection_date', $year);
                
            if ($month) {
                $statusQuery->whereMonth('inspection_date', $month);
            }
            
            $statusCounts[$status] = $statusQuery->count();
        }
        
        return [
            'total' => $total,
            'by_status' => $statusCounts,
            'approved' => $statusCounts['approved'] ?? 0,
            'rejected' => $statusCounts['rejected'] ?? 0,
            'completed' => $statusCounts['completed'] ?? 0,
            'cancelled' => $statusCounts['cancelled'] ?? 0,
        ];
    }

    /**
     * Helper: Get available months that have data
     */
    private function getAvailableMonths($inspectorId, $year)
    {
        $months = Inspection::where('inspector_id', $inspectorId)
            ->whereIn('status', self::STATUS_COMPLETED)
            ->whereYear('inspection_date', $year)
            ->selectRaw('MONTH(inspection_date) as month')
            ->distinct()
            ->orderBy('month')
            ->pluck('month')
            ->map(function($month) {
                return [
                    'value' => $month,
                    'name' => \Carbon\Carbon::create()->month($month)->format('F')
                ];
            });
        
        return $months;
    }
}