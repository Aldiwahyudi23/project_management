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
                return $statusCheck; // return error response
            }

            // ── 5. Parse & bersihkan results ─────────────────────
            $parsedResults = $this->parseResults($request->results);

            // ── 6. Forward ke Backend A ───────────────────────────
            $payload = [
                'inspection_id' => $inspection->inspection_id, // ID di sistem Backend A
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
            $inspection->status       = 'under_review';
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

    /**
     * Aturan:
     * - status 'in_progress' → hanya inspector yang sama boleh submit
     * - status 'revision'    → inspector mana pun boleh submit (re-inspeksi)
     * - status lainnya       → tolak
     */
    private function validateStatusAndInspector(Inspection $inspection, $user): true|\Illuminate\Http\JsonResponse
    {
        $allowedStatuses = ['in_progress', 'paused', 'revision'];

        if (!in_array($inspection->status, $allowedStatuses)) {
            return $this->error(
                "Inspeksi tidak dapat disimpan. Status saat ini: {$inspection->status_label}",
                422
            );
        }

        // Status revision → siapa pun boleh
        if ($inspection->status === 'revision') {
            return true;
        }

        // in_progress / paused → harus inspector yang sama
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
    // Mengubah struktur frontend → struktur siap kirim ke Backend A
    // ─────────────────────────────────────────────────────────────

    private function parseResults(array $results): array
    {
        $parsed = [];

        foreach ($results as $result) {
            $inspectionItemId = (int) $result['inspection_item_id'];
            $itemId = (int) $result['item_id'];
            $value            = $result['value'];

            // Skip value yang benar-benar kosong
            if ($this->isEmptyValue($value)) {
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
    // Mengurai value → status, note, extra_data, image_ids
    //
    // Struktur value dari frontend:
    //
    // [A] Nilai langsung (text/number/date)
    //     "Baret halus" | 12345 | "2026-02-11"
    //
    // [B] Array image langsung (input type image)
    //     [{"id":57}, {"id":58}]
    //
    // [C] Object dengan main (radio/checkbox/image+option)
    //     {
    //       "main": "rusak" | ["a","b"] | [{"id":64}],
    //       "nested": {
    //         "optionValue": {
    //           "textarea":   "catatan",
    //           "damage_ids": [1, 2],
    //           "image":      [{"id":60}]
    //         }
    //       },
    //       "imageNested": {
    //         "selectedOption": "B",
    //         "nested": {
    //           "aggregated": {
    //             "textarea":   "catatan",
    //             "damage_ids": [47],
    //           }
    //         }
    //       }
    //     }
    // ─────────────────────────────────────────────────────────────

    private function extractFields(mixed $value): array
    {
        $status    = null;
        $note      = null;
        $extraData = null;
        $imageIds  = [];

        // ── [A] Nilai langsung (string/number/date) ───────────────
        if (!is_array($value)) {
            $note = (string) $value;
            return [                          // ← ganti compact() dengan ini
                'status'     => $status,
                'note'       => $note,
                'extra_data' => $extraData,
                'image_ids'  => $imageIds,
            ];
        }

        // ── [B] Array image langsung ──────────────────────────────
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

        // ── [C] Object dengan 'main' ──────────────────────────────
        if (isset($value['main'])) {
            $main      = $value['main'];
            $nested    = $value['nested']      ?? [];
            $imgNested = $value['imageNested'] ?? null;

            // Status dari main
            if (is_array($main)) {
                if (!empty($main) && isset($main[0]['id'])) {
                    // Array of image objects: [{"id":64}]
                    $imageIds = collect($main)
                        ->pluck('id')
                        ->filter()
                        ->map(fn($id) => (int) $id)
                        ->values()
                        ->toArray();
                } elseif (!empty($main)) {
                    // Array of strings: ["NOT OK", "Repaint"]
                    $status = $main;
                }
            } else {
                $status = $main !== '' ? (string) $main : null;
            }

            // Dari nested
            [$nestedNote, $nestedDamageIds, $nestedImageIds] = $this->extractFromNested($nested);

            // Dari imageNested
            if ($imgNested) {
                $selectedOption = $imgNested['selectedOption'] ?? null;
                if ($selectedOption !== null && $selectedOption !== '') {
                    $status = is_array($selectedOption)
                        ? $selectedOption
                        : (string) $selectedOption;
                }

                [$imgNote, $imgDamageIds, $imgNestedImageIds] = $this->extractFromNested($imgNested['nested'] ?? []);

                if ($nestedNote === null && $imgNote !== null) $nestedNote = $imgNote;
                $nestedDamageIds = array_merge($nestedDamageIds, $imgDamageIds);
                $nestedImageIds  = array_merge($nestedImageIds, $imgNestedImageIds);
            }

            if ($note === null) $note = $nestedNote;
            $imageIds = array_merge($imageIds, $nestedImageIds);

            // extra_data: damage_ids
            $allDamageIds = array_values(array_unique($nestedDamageIds));
            if (!empty($allDamageIds)) {
                $extraData = ['damage_ids' => $allDamageIds];
            }
        }

        // Fallback
        return [
            'status'     => $status,
            'note'       => $note,
            'extra_data' => $extraData,
            'image_ids'  => array_values(array_unique($imageIds)),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // EXTRACT FROM NESTED
    // Menggali note, damage_ids, image_ids dari nested/imageNested
    // ─────────────────────────────────────────────────────────────

    private function extractFromNested(array $nested): array
    {
        $note      = null;
        $damageIds = [];
        $imageIds  = [];

        foreach ($nested as $optionValue => $optionData) {
            if (!is_array($optionData)) continue;

            // Textarea → note (ambil pertama yang tidak null/kosong)
            if ($note === null && !empty($optionData['textarea'])) {
                $note = (string) $optionData['textarea'];
            }

            // Damage IDs
            if (!empty($optionData['damage_ids']) && is_array($optionData['damage_ids'])) {
                foreach ($optionData['damage_ids'] as $did) {
                    $damageIds[] = (int) $did;
                }
            }

            // Image IDs dari nested option
            if (!empty($optionData['image']) && is_array($optionData['image'])) {
                foreach ($optionData['image'] as $img) {
                    if (!empty($img['id'])) {
                        $imageIds[] = (int) $img['id'];
                    }
                }
            }
        }

        return [$note, $damageIds, $imageIds];
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null || $value === '') return true;
        if (is_array($value) && empty($value)) return true;

        // Array berisi object kosong: [{}]
        if (is_array($value) && array_is_list($value)) {
            $filtered = array_filter($value, fn($v) => !empty($v));
            if (empty($filtered)) return true;
        }

        // Object dengan main null/kosong
        if (is_array($value) && isset($value['main'])) {
            $main = $value['main'];
            if ($main === null || $main === '') return true;
            if (is_array($main) && empty($main)) return true;
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