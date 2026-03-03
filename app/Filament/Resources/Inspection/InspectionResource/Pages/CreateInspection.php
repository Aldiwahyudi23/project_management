<?php

namespace App\Filament\Resources\Inspection\InspectionResource\Pages;

use App\Filament\Resources\Inspection\InspectionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateInspection extends CreateRecord
{
    protected static string $resource = InspectionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // Set submitted_by
            $data['submitted_by'] = Auth::id();
            $data['status'] = 'draft';
            
            // SET DEFAULT SEMENTARA UNTUK inspection_id (akan diupdate di afterCreate)
            $data['inspection_id'] = 0; // ATAU NULL? Tergantung migration

            $data['customer_id']; //mengirim data customer_id

            // Gabungkan plat nomor hanya jika semua komponen ada
            if (!empty($data['license_plate_prefix']) && 
                !empty($data['license_plate_number']) && 
                !empty($data['license_plate_suffix'])) {
                $data['license_plate'] = $data['license_plate_prefix'] . ' ' . 
                                         $data['license_plate_number'] . ' ' . 
                                         $data['license_plate_suffix'];
            } else {
                $data['license_plate'] = null;
            }
            
            // Hapus field temporary yang tidak ada di tabel
            $temporaryFields = [
                'license_plate_prefix', 'license_plate_number', 'license_plate_suffix',
                'vehicle_brand_id', 'vehicle_model_id', 'vehicle_type_id', 
                'vehicle_cc', 'vehicle_year', 'transmission_id', 'fuel_type', 
                'market_period', 'template_id', 'template_notes',
                'region_id', 'confirm_submit'
            ];
            
            foreach ($temporaryFields as $field) {
                unset($data[$field]);
            }
            
            // Filter data untuk memastikan tidak ada nilai null yang tidak diinginkan
            $data = array_filter($data, function ($value) {
                return !is_null($value) && $value !== '';
            });
            
            // Simpan ke database lokal
            $record = static::getModel()::create($data);
            
            return $record;
        });
    }

      protected function afterCreate(): void
    {
        // Simpan data asli untuk digunakan di afterCreate
        $originalData = $this->data;
        
        // Panggil afterCreate dari resource untuk handle eksternal
        InspectionResource::afterCreate($this->record, $originalData);
        
        // UPDATE inspection_id SETELAH external inspection dibuat
        if ($this->record->externalInspection) {
            $this->record->update([
                'inspection_id' => $this->record->externalInspection->id
            ]);
        }
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Inspeksi berhasil dibuat';
    }
}