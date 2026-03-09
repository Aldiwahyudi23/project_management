<?php

namespace App\Http\Controllers\Api\AppInspection\Job;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Services\InspectionApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FormInspectionController extends Controller
{
    public function __construct(
        protected InspectionApiService $inspectionApi
    ) {
        //
    }

    /**
     * Mendapatkan form inspeksi berdasarkan ID inspeksi lokal
     * 
     * @param int $id ID inspeksi dari tabel inspections lokal
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFormInspection($id)
    {
        try {
            // Validasi user login
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            // Cari data inspeksi lokal
            $inspection = Inspection::
                // where('inspector_id', $user->id)
                // ->
                find($id);

            // Cek apakah inspeksi ditemukan
            if (!$inspection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data inspeksi tidak ditemukan'
                ], 404);
            }

            // Validasi status - hanya bisa mulai jika status draft atau accepted
            $allowedStatuses = ['accepted', 'arrived','in_progress', 'revision', 'paused'];
            if (!in_array($inspection->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat memulai inspeksi dengan status ' . $inspection->status_label
                ], 400);
            }

            // Panggil API eksternal untuk mendapatkan form template
            $result = $this->inspectionApi->getFormInspection($inspection->inspection_id);

            // Cek response dari API eksternal
            if (!isset($result['success']) || !$result['success']) {
                $errorMessage = $result['message'] ?? 'Gagal mengambil form inspeksi';
                $errorDetail = $result['error'] ?? null;
                
                // // Log error untuk debugging
                // \Log::error('Failed to get form inspection from external API', [
                //     'inspection_id' => $inspection->inspection_id,
                //     'error' => $errorDetail
                // ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'debug' => config('app.debug') ? $errorDetail : null
                ], 500);
            }

            // Update status inspeksi menjadi 'in_progress' jika berhasil mendapatkan form
            if ($inspection->status === 'accepted' || $inspection->status === 'arrived' || $inspection->status === 'revision' || $inspection->status === 'paused' ) {
                $inspection->status = 'in_progress';
                $inspection->save();
                
                // // Log aktivitas
                // \Log::info('Inspection started', [
                //     'inspection_id' => $inspection->id,
                //     'external_id' => $inspection->inspection_id,
                //     'user_id' => $user->id
                // ]);
            }

            // Return success response dengan data form
            return response()->json([
                'success' => true,
                'message' => 'Form inspeksi berhasil diambil',
                'data' => [
                    'inspection' => [
                        'id' => $inspection->id,
                        'inspection_id' => $inspection->inspection_id,
                        'status' => $inspection->status,
                        'status_label' => $inspection->status_label,
                        // 'status_color' => $inspection->status_color,
                        'inspection_date' => $inspection->inspection_date,
                        // 'customer_id' => $inspection->customer_id,
                        // 'reference' => $inspection->reference,
                    ],
                    'form' => $result['data'] ?? $result
                ]
            ]);

        } catch (\Exception $e) {
            // // Log error untuk debugging
            // \Log::error('Exception in getFormInspection: ' . $e->getMessage(), [
            //     'id' => $id,
            //     'trace' => $e->getTraceAsString()
            // ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil form inspeksi',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'inspection_id'        => 'required|integer',
            'inspection_item_id'   => 'required|integer',
            'item_id'              => 'required|integer',
            'images'               => 'required',
            // selected_option_value WAJIB dikirim, validasi ada/tidaknya
            // akan ditentukan di Backend A berdasarkan input_type SectionItem
            'selected_option_value' => 'nullable|string',
        ]);

        $files = $request->file('images');
        if (!is_array($files)) {
            $files = [$files];
        }

        $result = $this->inspectionApi->uploadImage(
            $request->only(
                'inspection_id',
                'inspection_item_id',
                'item_id',
                'selected_option_value'
            ),
            $files
        );

        // Teruskan status code dari Backend A ke Frontend
        $statusCode = $result['status_code'] ?? 200;
        return response()->json($result, $statusCode);
    }

    public function deleteImage(int $id)
    {

        $result = $this->inspectionApi->deleteImage($id);

        return response()->json($result);
    }

      /**
     * Delete inspection result + all images in one item
     * (Frontend Vue calls this)
     */
    public function deleteItem(Request $request, int $inspectionId, int $itemId)
    {
        try {

            $result = $this->inspectionApi
                ->deleteInspectionItem($inspectionId, $itemId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Delete failed',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully',
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Unexpected server error',
            ], 500);
        }
    }

    
// ─────────────────────────────────────────────────────────────
    // ENTRY POINT
    // ─────────────────────────────────────────────────────────────

    /**
     * Menyimpan hasil inspeksi dari Frontend.
     *
     * Alur:
     * 1. Autentikasi user
     * 2. Validasi input dasar
     * 3. Cari Inspection local (Backend B)
     * 4. Validasi status & kepemilikan inspector
     * 5. Parse & validasi results
     * 6. Forward ke Backend A (sudah dalam format siap simpan)
     * 7. Update status local jika sukses
     */
    public function saveFormInspection(Request $request, int $inspectionId)
    {
        try {
            // ── 1. Autentikasi ────────────────────────────────────
            $user = Auth::user();
            if (!$user) {
                return $this->error('User tidak terautentikasi', 401);
            }

            // ── 2. Validasi input dasar ───────────────────────────
            $validator = Validator::make($request->all(), [
                'results'                          => 'required|array|min:1',
                'results.*.inspection_item_id'     => 'required|integer|min:1',
                'results.*.item_id'                => 'nullable|integer',
                'results.*.value'                  => 'nullable',
            ]);

            if ($validator->fails()) {
                return $this->error('Validasi gagal', 422, $validator->errors());
            }

            // ── 3. Cari Inspection local ──────────────────────────
            $inspection = Inspection::find($inspectionId);

            if (!$inspection) {
                return $this->error('Data inspeksi tidak ditemukan', 404);
            }

            // ── 4. Validasi status & inspector ────────────────────
            $statusCheck = $this->validateStatusAndInspector($inspection, $user);
            if ($statusCheck !== true) {
                return $statusCheck;
            }

            // ── 5. Parse & bersihkan results ─────────────────────
            $parsedResults = $this->parseResults($request->results);

            // ── 6. Forward ke Backend A ───────────────────────────
            $payload = [
                'inspection_id' => $inspection->inspection_id,
                'results'       => $parsedResults,
            ];

            $apiResult = $this->inspectionApi->submitInspectionForm($payload);

            if (!($apiResult['success'] ?? false)) {
                return $this->error(
                    $apiResult['message'] ?? 'Gagal menyimpan ke server inspeksi',
                    502
                );
            }

            // ── 7. Update status local ────────────────────────────
            $inspection->status = 'under_review';
            $inspection->save();

            return response()->json([
                'success' => true,
                'message' => 'Inspeksi berhasil disimpan',
                'data'    => [
                    'inspection_id' => $inspection->id,
                    'status'        => $inspection->status,
                    'status_label'  => $inspection->status_label,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('SaveFormInspection error', [
                'inspection_id' => $inspectionId,
                'user_id'       => Auth::id(),
                'message'       => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);

            return $this->error(
                'Terjadi kesalahan saat menyimpan inspeksi',
                500,
                config('app.debug') ? ['debug' => $e->getMessage()] : null
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // VALIDASI STATUS & INSPECTOR
    // ─────────────────────────────────────────────────────────────

    private function validateStatusAndInspector(Inspection $inspection, $user): true|\Illuminate\Http\JsonResponse
    {
        $allowedStatuses = ['in_progress', 'paused', 'revision'];

        if (!in_array($inspection->status, $allowedStatuses)) {
            return $this->error(
                "Inspeksi tidak dapat disimpan. Status saat ini: {$inspection->status_label}",
                422
            );
        }

        if ($inspection->status === 'revision') {
            return true;
        }

        if ($inspection->inspector_id !== $user->id) {
            return $this->error(
                'Anda tidak memiliki akses untuk menyimpan inspeksi ini',
                403
            );
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // PARSE RESULTS
    // Mengubah struktur frontend (flat) → struktur siap kirim ke Backend A
    //
    // Format value dari frontend (flat, post-refactor):
    //
    // [A] Nilai langsung (text/number/date/currency/dll)
    //     "Baret halus" | 12345 | "2026-02-11"
    //
    // [B] Array image langsung (input type = image, tanpa show_option)
    //     [{"id": 57}, {"id": 58}]
    //
    // [C] Object flat (radio / select / checkbox, atau image + show_option)
    //     {
    //       "status":     "Ada" | ["Ada", "Rusak"] | null,
    //       "note":       "catatan" | null,
    //       "image":      [{"id": 60}] | null,
    //       "damage_ids": [1, 2]
    //     }
    // ─────────────────────────────────────────────────────────────

    private function parseResults(array $results): array
    {
        $parsed = [];

        foreach ($results as $result) {
            $inspectionItemId = (int) $result['inspection_item_id'];
            $itemId           = isset($result['item_id']) ? (int) $result['item_id'] : null;
            $value            = $result['value'] ?? null;

            // Value kosong → kirim null payload ke Backend A agar data lama dibersihkan
            if ($this->isEmptyValue($value)) {
                $parsed[] = [
                    'inspection_item_id' => $inspectionItemId,
                    'item_id'            => $itemId,
                    'status'             => null,
                    'note'               => null,
                    'extra_data'         => null,
                    'image_ids'          => [],
                ];
                continue;
            }

            $parsed[] = [
                'inspection_item_id' => $inspectionItemId,
                'item_id'            => $itemId,
                ...$this->extractFields($value),
            ];
        }

        return $parsed;
    }

    // ─────────────────────────────────────────────────────────────
    // EXTRACT FIELDS
    // Mengurai flat value → status, note, extra_data, image_ids
    // ─────────────────────────────────────────────────────────────

    private function extractFields(mixed $value): array
    {
        $status    = null;
        $note      = null;
        $extraData = null;
        $imageIds  = [];

        // ── [A] Nilai langsung (string / number) ──────────────────
        // Contoh: "Baret halus", 12345, "2026-02-11"
        if (!is_array($value)) {
            $note = (string) $value;

            return [
                'status'     => $status,
                'note'       => $note,
                'extra_data' => $extraData,
                'image_ids'  => $imageIds,
            ];
        }

        // ── [B] Array image langsung ──────────────────────────────
        // Contoh: [{"id": 57, "image_url": "..."}, {"id": 58}]
        // Ciri: array list (bukan assoc), elemen pertama punya key 'id'
        if (array_is_list($value) && !empty($value) && isset($value[0]['id'])) {
            $imageIds = collect($value)
                ->pluck('id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->values()
                ->toArray();

            return [
                'status'     => $status,
                'note'       => $note,
                'extra_data' => $extraData,
                'image_ids'  => $imageIds,
            ];
        }

        // ── [C] Object flat (radio / select / checkbox / image+show_option) ──
        // Struktur: { status, note, image, damage_ids }
        // - status: string (radio/select) | string[] (checkbox) | null (image+show_option yang belum pilih)
        // - note:       string | null
        // - image:      [{"id": N, ...}] | null
        // - damage_ids: [1, 2] | []

        // Status
        if (array_key_exists('status', $value)) {
            $rawStatus = $value['status'];

            if (is_array($rawStatus)) {
                // Checkbox: ["Ada", "Rusak"]
                $filtered = array_values(array_filter($rawStatus, fn($v) => $v !== null && $v !== ''));
                $status   = !empty($filtered) ? $filtered : null;
            } elseif ($rawStatus !== null && $rawStatus !== '') {
                // Radio / select: "Ada"
                $status = (string) $rawStatus;
            }
        }

        // Note (dari textarea nested)
        if (!empty($value['note']) && is_string($value['note'])) {
            $note = $value['note'];
        }

        // Image ids (dari nested image)
        if (!empty($value['image']) && is_array($value['image'])) {
            $imageIds = collect($value['image'])
                ->pluck('id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->values()
                ->toArray();
        }

        // Damage ids → extra_data
        if (!empty($value['damage_ids']) && is_array($value['damage_ids'])) {
            $damageIds = array_values(array_unique(array_map('intval', $value['damage_ids'])));
            if (!empty($damageIds)) {
                $extraData = ['damage_ids' => $damageIds];
            }
        }

        return [
            'status'     => $status,
            'note'       => $note,
            'extra_data' => $extraData,
            'image_ids'  => array_values(array_unique($imageIds)),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null || $value === '') return true;
        if (is_array($value) && empty($value)) return true;

        // Array image kosong: [{}] atau [{"image_url": "..."}] tanpa id
        if (is_array($value) && array_is_list($value)) {
            $withId = array_filter($value, fn($v) => is_array($v) && !empty($v['id']));
            if (empty($withId)) return true;
        }

        // Object flat: status null/kosong DAN note null DAN image kosong
        if (is_array($value) && !array_is_list($value)) {
            $status    = $value['status']     ?? null;
            $note      = $value['note']       ?? null;
            $image     = $value['image']      ?? null;
            $damageIds = $value['damage_ids'] ?? [];

            $statusEmpty = $status === null || $status === ''
                || (is_array($status) && empty($status));
            $noteEmpty   = $note === null || $note === '';
            $imageEmpty  = empty($image) || (is_array($image) && empty(array_filter($image, fn($v) => !empty($v['id']))));
            $damageEmpty = empty($damageIds);

            return $statusEmpty && $noteEmpty && $imageEmpty && $damageEmpty;
        }

        return false;
    }

    private function error(string $message, int $status = 400, mixed $extra = null): \Illuminate\Http\JsonResponse
    {
        $body = ['success' => false, 'message' => $message];
        if ($extra !== null) $body = array_merge($body, (array) $extra);
        return response()->json($body, $status);
    }

}