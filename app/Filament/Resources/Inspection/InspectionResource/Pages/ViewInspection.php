<?php

namespace App\Filament\Resources\Inspection\InspectionResource\Pages;

use App\Filament\Resources\Inspection\InspectionResource;
use App\Filament\Resources\Inspection\MasterData\CustomerResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Fieldset;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\MasterData\Region;
use App\Models\MasterData\RegionTeam;
use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;

class ViewInspection extends ViewRecord
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // TOMBOL ALIHKAN TUGAS - HANYA UNTUK STATUS DRAFT
            Action::make('assignInspector')
                ->label(fn ($record) => $record->inspector_id ? 'Ubah Penugasan' : 'Alihkan Tugas')
                ->icon('heroicon-o-user-plus')
                ->color('warning')
                ->visible(fn ($record) => $record->status === 'draft')
                ->modalHeading('Penugasan Inspektor')
                ->modalDescription('Pilih region dan inspector untuk inspeksi ini')
                ->modalWidth('2xl')
                ->modalSubmitActionLabel('Simpan Penugasan')
                ->modalCancelActionLabel('Batal')
                ->form([
                    Forms\Components\Section::make('Pilih Region & Inspector')
                        ->schema([
                            Forms\Components\Select::make('region_id')
                                ->label('Region')
                                ->options(function ($record) {
                                    // AMBIL AREA DARI DATA SELLER
                                    $sellerAreas = [];
                                    if ($record && $record->sellers && $record->sellers->count() > 0) {
                                        $sellerAreas = $record->sellers
                                            ->pluck('inspection_area')
                                            ->filter()
                                            ->map(function ($area) {
                                                return trim($area);
                                            })
                                            ->values()
                                            ->toArray();
                                    }
                                    
                                    // QUERY REGION - FILTER BERDASARKAN AREA SELLER
                                    $query = Region::where('is_active', true)
                                        ->whereNotNull('name');
                                    
                                    // JIKA ADA AREA SELLER, FILTER YANG COCOK
                                    if (!empty($sellerAreas)) {
                                        $query->where(function ($q) use ($sellerAreas) {
                                            foreach ($sellerAreas as $area) {
                                                $q->orWhere('name', 'LIKE', "%{$area}%")
                                                  ->orWhereRaw('LOWER(name) = LOWER(?)', [$area]);
                                            }
                                        });
                                    }
                                    
                                    $regions = $query->orderBy('name')->pluck('name', 'id')->toArray();
                                    
                                    // JIKA TIDAK ADA YANG COCOK, TAMPILKAN SEMUA REGION
                                    if (empty($regions)) {
                                        $regions = Region::where('is_active', true)
                                            ->whereNotNull('name')
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    }
                                    
                                    return $regions;
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('inspector_id', null))
                                ->helperText(function ($record) {
                                    if ($record && $record->sellers && $record->sellers->count() > 0) {
                                        $areas = $record->sellers->pluck('inspection_area')->filter()->unique();
                                        if ($areas->isNotEmpty()) {
                                            return '🎯 Region disarankan berdasarkan area: ' . $areas->implode(', ');
                                        }
                                    }
                                    return 'Pilih region untuk menampilkan inspector yang tersedia';
                                })
                                ->default(function ($record) {
                                    // AUTO SELECT REGION BERDASARKAN AREA SELLER PERTAMA
                                    if ($record && $record->sellers && $record->sellers->count() > 0) {
                                        $firstArea = $record->sellers->first()?->inspection_area;
                                        if ($firstArea) {
                                            $region = Region::where('is_active', true)
                                                ->where(function ($q) use ($firstArea) {
                                                    $q->where('name', 'LIKE', "%{$firstArea}%")
                                                      ->orWhereRaw('LOWER(name) = LOWER(?)', [$firstArea]);
                                                })
                                                ->first();
                                            return $region?->id;
                                        }
                                    }
                                    return null;
                                }),
                            
                            Forms\Components\Select::make('inspector_id')
                                ->label('Inspector')
                                ->options(function (Get $get, $record) {

                                    $regionId = $get('region_id');

                                    // =========================
                                    // MODE EDIT (region belum kepilih tapi ada inspector lama)
                                    // =========================
                                    if (!$regionId && $record?->inspector_id) {
                                        return User::where('id', $record->inspector_id)
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    }

                                    // =========================
                                    // MODE CREATE / REGION DIPILIH
                                    // =========================
                                    if (!$regionId) {
                                        return [];
                                    }

                                    return User::whereHas('regionTeams', function ($query) use ($regionId) {
                                            $query->where('region_id', $regionId);
                                        })
                                        ->whereHas('roles', function ($query) {
                                            $query->where('name', 'inspector');
                                        })
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn (Get $get, $record) =>
                                    !($get('region_id') || $record?->region_id)
                                )
                                ->helperText('Pilih inspector yang akan ditugaskan'),

                            
                            Forms\Components\DateTimePicker::make('inspection_date')
                                ->label('Tanggal & Waktu Inspeksi')
                                ->required()
                                ->seconds(false)
                                ->displayFormat('d M Y H:i')
                                // ->minDate(now())
                                ->maxDate(now()->addMonths(3))
                                ->default(fn ($record) => $record->inspection_date ?? now()->addDay()->setTime(9, 0))
                                ->helperText('Jadwalkan waktu pelaksanaan inspeksi'),
                                
                        ]),
                ])
                ->action(function (array $data, $record): void {
                    // SIMPAN DATA PENUGASAN
                    DB::transaction(function () use ($data, $record) {
                        $oldInspectorId = $record->inspector_id;
                        $oldDate = $record->inspection_date;
                        
                        // Update record lokal
                        $record->update([
                            'inspector_id' => $data['inspector_id'],
                            'inspection_date' => $data['inspection_date'],
                        ]);
                        
                        // Update external inspection jika ada
                        if ($record->externalInspection) {
                            $record->externalInspection->update([
                                'inspection_date' => $data['inspection_date'],
                            ]);
                        }
                        
                         // LOG ACTIVITY DENGAN FORMAT YANG CONSISTENT
                        activity()
                            ->performedOn($record)
                            ->causedBy(Auth::user())
                            ->withProperties([
                                'old' => [
                                    'old_inspector_id' => $oldInspectorId,
                                    'old_date' => $oldDate?->format('Y-m-d H:i'),
                                ],
                                'new' => [
                                    'new_inspector_id' => $data['inspector_id'],
                                    'new_date' => $data['inspection_date'],
                                    'region_id' => $data['region_id'] ?? null,
                                ]
                            ])
                            ->log($oldInspectorId ? 'inspektor_diubah' : 'inspektor_ditugaskan');
                       
                    });
                    
                    // Refresh record
                    $record->refresh();
                    
                    // Notifikasi sukses
                    $inspectorName = User::find($data['inspector_id'])?->name ?? 'Inspector';
                    $regionName = Region::find($data['region_id'])?->name ?? 'Region';
                    
                    \Filament\Notifications\Notification::make()
                        ->title('✅ Inspektor berhasil ditugaskan')
                        ->body("**{$inspectorName}** dari region **{$regionName}** telah ditugaskan untuk inspeksi ini.")
                        ->success()
                        ->send();
                }),

            Action::make('addSeller')
                ->label('Tambah Seller / PIC')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->visible(fn ($record): bool => 
                    $record->status === 'draft' && // Hanya di draft
                    (!$record->sellers || $record->sellers->count() === 0) // Belum ada seller
                )
                ->modalHeading('Tambah Data Seller / PIC')
                ->modalDescription('Lengkapi data pemegang unit dan lokasi inspeksi')
                ->modalWidth('3xl')
                ->modalSubmitActionLabel('Simpan Seller')
                ->modalCancelActionLabel('Batal')
                ->form([
                    Forms\Components\Section::make('Data Relasi')
                        ->schema([
                            Forms\Components\Select::make('customer_id')
                                ->label('Customer')
                                ->relationship('customer', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->default(fn ($record) => $record->customer_id)
                                ->disabled()
                                ->dehydrated(),
                            
                            Forms\Components\Hidden::make('inspection_id')
                                ->default(fn ($record) => $record->id),
                        ]),
                    
                    Forms\Components\Section::make('Data Lokasi Inspeksi')
                        ->schema([
                            Forms\Components\Select::make('inspection_area')
                                ->label('Area Inspeksi')
                                ->options(
                                    \App\Models\MasterData\Region::where('is_active', 1)
                                        ->orderBy('name')
                                        ->pluck('name', 'name')
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                ->placeholder('Pilih area inspeksi')
                                ->helperText('Area ini akan digunakan untuk rekomendasi region penugasan'),
                            
                            Forms\Components\Textarea::make('inspection_address')
                                ->label('Alamat Inspeksi')
                                ->rows(3)
                                ->maxLength(65535)
                                ->required()
                                ->placeholder('Masukkan alamat lengkap inspeksi'),
                            
                            Forms\Components\TextInput::make('link_maps')
                                ->label('Link Google Maps')
                                ->url()
                                ->required()
                                ->rule('active_url')
                                ->maxLength(65535)
                                ->prefix('🔗')
                                ->placeholder('https://maps.google.com/...')
                                ->helperText('Pastikan link Google Maps valid dan dapat diakses'),
                        ])
                        ->columns(2),
                    
                    Forms\Components\Section::make('Data Pemegang Unit')
                        ->schema([
                            Forms\Components\TextInput::make('unit_holder_name')
                                ->label('Nama Pemegang Unit')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Masukkan nama pemegang unit'),
                            
                            Forms\Components\TextInput::make('unit_holder_phone')
                                ->label('No. HP Pemegang Unit')
                                ->required()
                                ->tel()
                                ->numeric()
                                ->minLength(9)
                                ->maxLength(13)
                                ->prefix('+62')
                                ->rule('regex:/^[0-9]{9,13}$/')
                                ->validationMessages([
                                    'regex' => 'Nomor HP harus 9–13 digit angka.',
                                ])
                                ->placeholder('81234567890'),

                            // ======= Data Hidden ======
                            Forms\Components\Hidden::make('status')
                                ->default('active'),

                            Forms\Components\Hidden::make('customer_id')
                                ->default(fn ($record) => $record->customer_id),
                            
                            Forms\Components\Hidden::make('inspection_id')
                                ->default(fn ($record) => $record->id),
                                
                        ])
                        ->columns(2),
                    
                ])
                ->action(function (array $data, $record): void {
                    DB::transaction(function () use ($data, $record) {
                        // 1. BUAT SELLER BARU
                        $seller = $record->sellers()->create([
                            'inspection_id' => $record->id,
                            'customer_id' => $record->customer_id,
                            'unit_holder_name' => $data['unit_holder_name'],
                            'unit_holder_phone' => $data['unit_holder_phone'],
                            'inspection_area' => $data['inspection_area'],
                            'inspection_address' => $data['inspection_address'],
                            'link_maps' => $data['link_maps'],
                            'settings' => $data['settings'] ?? null,
                            'status' => $data['status'] ?? 'active',
                        ]);
                        
                        // 2. LOG ACTIVITY
                        activity()
                            ->performedOn($record)
                            ->causedBy(Auth::user())
                            ->withProperties([
                                'seller_id' => $seller->id,
                                'seller_name' => $data['unit_holder_name'],
                                'inspection_area' => $data['inspection_area'],
                                'method' => 'manual_add_seller',
                            ])
                            ->log('seller_ditambahkan');
                        
                        // 3. NOTIFIKASI
                        \Filament\Notifications\Notification::make()
                            ->title('✅ Seller berhasil ditambahkan')
                            ->body("**{$data['unit_holder_name']}** telah ditambahkan sebagai PIC/Seller untuk inspeksi ini.")
                            ->success()
                            ->send();
                    });
                    
                    // 4. REFRESH RECORD
                    $record->refresh();
                }),
            
            Action::make('cancel')
                ->label('Batalkan Inspeksi')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Batalkan Inspeksi')
                ->modalDescription('Apakah Anda yakin ingin membatalkan inspeksi ini? Tindakan ini tidak dapat diubah dan akan memperbarui status menjadi DIBATALKAN.')
                ->modalSubmitActionLabel('Ya, Batalkan')
                ->modalCancelActionLabel('Tidak, Kembali')
                ->visible(fn ($record): bool => 
                    $record->status !== 'cancelled' && // Belum dibatalkan
                    $record->status !== 'completed' && // Belum selesai
                    $record->status !== 'approved'      // Belum disetujui
                )
                ->form([
                    Forms\Components\Textarea::make('cancellation_reason')
                        ->label('Alasan Pembatalan')
                        ->placeholder('Harap isi alasan mengapa inspeksi ini dibatalkan...')
                        ->required()
                        ->rows(4)
                        ->maxLength(65535)
                        ->helperText('Alasan ini akan disimpan ke catatan inspeksi dan sistem audit.'),
                ])
                ->action(function (array $data, $record): void {
                    DB::transaction(function () use ($data, $record) {
                        $reason = $data['cancellation_reason'];
                        $timestamp = now();
                        $cancelledBy = Auth::user()->name ?? 'System';
                        
                        // 1. UPDATE RECORD LOKAL (Inspection)
                        $oldStatus = $record->status;
                        $record->update([
                            'status' => 'cancelled',
                            'notes' => $record->notes 
                                ? $record->notes . "\n\n[DIBATALKAN pada {$timestamp->format('d M Y H:i')} oleh {$cancelledBy}]: {$reason}"
                                : "[DIBATALKAN pada {$timestamp->format('d M Y H:i')} oleh {$cancelledBy}]: {$reason}",
                        ]);

                        // 2. UPDATE RECORD EKSTERNAL (DirectDBInspection)
                        if ($record->externalInspection) {
                            $externalNotes = $record->externalInspection->notes ?? '';
                            $record->externalInspection->update([
                                'status' => 'cancelled',
                                'notes' => $externalNotes
                                    ? $externalNotes . "\n\n[CANCELLED at {$timestamp->format('Y-m-d H:i:s')} by {$cancelledBy}]: {$reason}"
                                    : "[CANCELLED at {$timestamp->format('Y-m-d H:i:s')} by {$cancelledBy}]: {$reason}",
                            ]);
                        }

                        // 3. LOG ACTIVITY (Laravel Auditing)
                        if (method_exists($record, 'audit')) {
                            activity()
                                ->performedOn($record)
                                ->causedBy(Auth::user())
                                ->withProperties([
                                    'old_status' => $oldStatus,
                                    'new_status' => 'cancelled',
                                    'reason' => $reason,
                                    'method' => 'manual_cancellation',
                                    'timestamp' => $timestamp,
                                ])
                                ->log('inspeksi_dibatalkan');
                        }

                        // 4. NOTIFIKASI KE USER TERKAIT (Opsional)
                        if ($record->inspector_id) {
                            \Filament\Notifications\Notification::make()
                                ->title('Inspeksi Dibatalkan')
                                ->body("Inspeksi dengan ID #{$record->id} telah dibatalkan.\nAlasan: {$reason}")
                                ->danger()
                                ->sendToDatabase($record->inspector);
                        }
                    });
                    
                    // 5. REFRESH DAN NOTIFIKASI SUCCESS
                    $record->refresh();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Inspeksi Berhasil Dibatalkan')
                        ->body('Status inspeksi telah diperbarui menjadi DIBATALKAN.')
                        ->success()
                        ->send();
                }),
            // ACTION EDIT & DELETE
            // Actions\EditAction::make(),
            // Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // HEADER: STATUS BADGE DI ATAS
                Section::make('')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('inspection_id')
                                    ->label('ID Inspeksi')
                                    ->formatStateUsing(fn ($state): string => $state ? "#EXT-{$state}" : '-')
                                    ->badge()
                                    ->color('gray')
                                    ->size(TextEntry\TextEntrySize::Large),
                                
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn ($record) => $record->status_color)
                                    ->formatStateUsing(fn ($record) => $record->status_label)
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->alignEnd(),
                            ]),
                    ])
                    ->compact(),
                
                // SECTION 1: DATA CUSTOMER (INFO DEALER)
                Section::make('Informasi Customer')
                    ->description('Data pemilik kendaraan / customer')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('customer.name')
                                    ->label('Nama Customer')
                                    ->weight('semibold')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->url(fn ($record) => $record->customer_id ? CustomerResource::getUrl('view', ['record' => $record->customer_id]) : null)
                                    ->color('primary'),
                                
                                TextEntry::make('customer.phone')
                                    ->label('No. WhatsApp')
                                    ->icon('heroicon-m-phone')
                                    ->copyable()
                                    ->copyMessage('Nomor telepon disalin')
                                    ->formatStateUsing(fn ($state) => $state ?: '-'),
                                
                                TextEntry::make('customer.email')
                                    ->label('Email')
                                    ->icon('heroicon-m-envelope')
                                    ->copyable()
                                    ->copyMessage('Email disalin')
                                    ->formatStateUsing(fn ($state) => $state ?: '-'),
                                
                                TextEntry::make('reference')
                                    ->label('Nomor Referensi')
                                    ->icon('heroicon-m-document-text')
                                    ->formatStateUsing(fn ($state) => $state ?: '-')
                                    ->copyable()
                                    ->columnSpanFull(),
                            ]),
                        
                        TextEntry::make('customer.address')
                            ->label('Alamat Customer')
                            ->icon('heroicon-m-map-pin')
                            ->formatStateUsing(fn ($state) => $state ?: '-')
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->customer && !empty($record->customer->address)),
                    ])
                    ->collapsible(false)
                    ->compact(),
                
                // SECTION 2: DATA PIC / SELLER (INFO DEALER CONSULTANT)
                Section::make('Informasi PIC / Seller')
                    ->description('Data penanggung jawab unit / seller')
                    ->icon('heroicon-o-user-group')
                    ->schema(function ($record) {
                        // CEK APAKAH ADA SELLER
                        if (!$record->sellers || $record->sellers->count() === 0) {
                            return [
                                TextEntry::make('no_seller')
                                    ->label('')
                                    ->state('Belum ada data seller / PIC')
                                    ->icon('heroicon-o-information-circle')
                                    ->color('gray')
                                    ->columnSpanFull()
                                    ->alignCenter()
                                    ->size(TextEntry\TextEntrySize::Medium)
                            ];
                        }
                        
                        // BUILD FIELDSET UNTUK SETIAP SELLER
                        $schemas = [];
                        foreach ($record->sellers as $index => $seller) {
                            $schemas[] = Fieldset::make('Seller ' . ($index + 1))
                                ->schema([
                                    Grid::make(3)
                                        ->schema([
                                            TextEntry::make("sellers.{$index}.unit_holder_name")
                                                ->label('Nama PIC / Seller')
                                                ->weight('semibold')
                                                ->size(TextEntry\TextEntrySize::Medium)
                                                ->default('-')
                                                ->state(fn () => $seller->unit_holder_name ?? '-'),
                                            
                                            TextEntry::make("sellers.{$index}.unit_holder_phone")
                                                ->label('No. HP')
                                                ->icon('heroicon-m-phone')
                                                ->copyable()
                                                ->copyMessage('Nomor telepon disalin')
                                                ->state(fn () => $seller->unit_holder_phone ?? '-'),
                                            
                                            TextEntry::make("sellers.{$index}.inspection_area")
                                                ->label('Area Inspeksi')
                                                ->icon('heroicon-m-map')
                                                ->state(fn () => $seller->inspection_area ?? '-')
                                                ->badge()
                                                ->color('success'),
                                        ]),
                                    
                                    // ALAMAT INSPEKSI LENGKAP
                                    TextEntry::make("sellers.{$index}.inspection_address")
                                        ->label('Alamat Inspeksi')
                                        ->icon('heroicon-m-map-pin')
                                        ->state(fn () => $seller->inspection_address ?? '-')
                                        ->columnSpanFull(),
                                    
                                    // LINK GOOGLE MAPS
                                    TextEntry::make("sellers.{$index}.link_maps")
                                        ->label('Link Google Maps')
                                        ->icon('heroicon-m-map')
                                        ->state(fn () => $seller->link_maps ?? null)
                                        ->formatStateUsing(function ($state) {
                                            if (empty($state)) return '-';
                                            return new HtmlString('<a href="' . $state . '" target="_blank" class="text-primary-600 hover:underline flex items-center gap-1"><span class="underline">Buka Google Maps</span> <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg></a>');
                                        })
                                        ->columnSpanFull()
                                        ->visible(fn ($state) => !empty($state)),
                                ])
                                ->columns(2);
                        }
                        
                        return $schemas;
                    })
                    ->collapsible(false)
                    ->columns(1),
                
                // SECTION 3: DATA KENDARAAN (DARI EXTERNAL)
                Section::make('Data Kendaraan')
                    ->description('Spesifikasi kendaraan yang diinspeksi')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('externalInspection.license_plate')
                                    ->label('Plat Nomor')
                                    ->weight('semibold')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn ($state) => $state ?: '-'),
                                
                                TextEntry::make('externalInspection.vehicle_name')
                                    ->label('Nama Kendaraan')
                                    ->weight('semibold')
                                    ->formatStateUsing(fn ($state) => $state ?: '-'),
                            ]),
                        
                        Fieldset::make('Spesifikasi Detail')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('externalInspection.settings.brand_id')
                                            ->label('Brand')
                                            ->formatStateUsing(function ($state, $record) {
                                                if (!$state || !$record->externalInspection) return '-';
                                                $brand = \App\Models\DirectDB\VehicleData\Brand::find($state);
                                                return $brand?->name ?? '-';
                                            }),
                                        
                                        TextEntry::make('externalInspection.settings.model_id')
                                            ->label('Model')
                                            ->formatStateUsing(function ($state, $record) {
                                                if (!$state || !$record->externalInspection) return '-';
                                                $model = \App\Models\DirectDB\VehicleData\VehicleModel::find($state);
                                                return $model?->name ?? '-';
                                            }),
                                        
                                        TextEntry::make('externalInspection.settings.type_id')
                                            ->label('Type')
                                            ->formatStateUsing(function ($state, $record) {
                                                if (!$state || !$record->externalInspection) return '-';
                                                $type = \App\Models\DirectDB\VehicleData\VehicleType::find($state);
                                                return $type?->name ?? '-';
                                            }),
                                        
                                        TextEntry::make('externalInspection.settings.year')
                                            ->label('Tahun')
                                            ->formatStateUsing(fn ($state) => $state ?: '-'),
                                        
                                        TextEntry::make('externalInspection.settings.cc')
                                            ->label('CC')
                                            ->formatStateUsing(function ($state) {
                                                return $state ? number_format($state / 1000, 1) . 'L' : '-';
                                            }),
                                        
                                        TextEntry::make('externalInspection.settings.transmission_id')
                                            ->label('Transmisi')
                                            ->formatStateUsing(function ($state, $record) {
                                                if (!$state || !$record->externalInspection) return '-';
                                                $transmission = \App\Models\DirectDB\VehicleData\Transmission::find($state);
                                                return $transmission?->name ?? '-';
                                            }),
                                        
                                        TextEntry::make('externalInspection.settings.fuel_type')
                                            ->label('Bahan Bakar')
                                            ->formatStateUsing(function ($state) {
                                                $fuelLabels = [
                                                    'bensin' => 'Bensin',
                                                    'diesel' => 'Diesel',
                                                    'hybrid' => 'Hybrid',
                                                    'electric' => 'Electric',
                                                ];
                                                return $fuelLabels[$state] ?? $state ?? '-';
                                            }),
                                        
                                        TextEntry::make('externalInspection.settings.market_period')
                                            ->label('Market Period')
                                            ->formatStateUsing(fn ($state) => $state ? 'Periode: ' . $state : '-'),
                                    ]),
                            ])
                            ->columns(2),
                    ])
                    ->collapsible(true)
                    ->collapsed(false),
                
                // SECTION 4: INSPEKTOR & JADWAL (DENGAN BADGE REGION)
                Section::make('Penugasan Inspektor')
                    ->description('Data inspector dan jadwal inspeksi')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('inspector.name')
                                    ->label('Nama Inspector')
                                    ->weight('semibold')
                                    ->icon('heroicon-m-user')
                                    ->formatStateUsing(fn ($state) => $state ?: 'Belum ditugaskan')
                                    ->badge(fn ($state) => !empty($state))
                                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                                
                                TextEntry::make('inspector.regionTeams.region.name')
                                    ->label('Region')
                                    ->icon('heroicon-m-map')
                                    ->formatStateUsing(function ($state, $record) {
                                        if ($record->inspector_id) {
                                            $region = RegionTeam::where('user_id', $record->inspector_id)
                                                ->with('region')
                                                ->first();
                                            return $region?->region?->name ?? '-';
                                        }
                                        return '-';
                                    })
                                    ->badge()
                                    ->color('info'),
                                
                                TextEntry::make('inspection_date')
                                    ->label('Tanggal & Waktu')
                                    ->icon('heroicon-m-calendar')
                                    ->dateTime('d M Y H:i'),
                                
                                IconEntry::make('inspection_date')
                                    ->label('Status Jadwal')
                                    ->icon(fn ($record) => 
                                        $record->inspection_date && $record->inspection_date->isToday() ? 'heroicon-o-check-circle' :
                                        ($record->inspection_date && $record->inspection_date->isFuture() ? 'heroicon-o-clock' : 'heroicon-o-x-circle')
                                    )
                                    ->color(fn ($record) =>
                                        $record->inspection_date && $record->inspection_date->isToday() ? 'success' :
                                        ($record->inspection_date && $record->inspection_date->isFuture() ? 'warning' : 'danger')
                                    )
                                    ->label(fn ($record) =>
                                        $record->inspection_date && $record->inspection_date->isToday() ? 'Hari ini' :
                                        ($record->inspection_date && $record->inspection_date->isFuture() ? 'Akan datang' : 'Sudah lewat')
                                    ),
                            ]),
                        
                        // TAMPILAN RINGKASAN JIKA BELUM ADA INSPEKTOR
                        TextEntry::make('no_inspector')
                            ->label('')
                            ->state('🔔 Belum ada inspector yang ditugaskan. Klik tombol "Alihkan Tugas" di atas untuk menugaskan inspector.')
                            ->icon('heroicon-o-information-circle')
                            ->color('warning')
                            ->columnSpanFull()
                            ->alignCenter()
                            ->visible(fn ($record) => !$record->inspector_id && $record->status === 'draft'),
                    ])
                    ->collapsible(false),
                
                // SECTION 5: TEMPLATE INSPEKSI
                Section::make('Template Inspeksi')
                    ->description('Template yang digunakan')
                    ->icon('heroicon-o-document-duplicate')
                    ->schema([
                        TextEntry::make('externalInspection.template.name')
                            ->label('Nama Template')
                            ->weight('semibold')
                            ->badge()
                            ->color('primary')
                            ->formatStateUsing(fn ($state) => $state ?: '-'),
                        
                        TextEntry::make('externalInspection.template.description')
                            ->label('Deskripsi')
                            ->formatStateUsing(fn ($state) => $state ?: '-')
                            ->visible(fn ($state) => !empty($state)),
                    ])
                    ->collapsible(true)
                    ->collapsed(true),
                
                // SECTION 6: INFORMASI AUDIT
                Section::make('Informasi Audit')
                    ->description('Data pembuatan dan perubahan')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Dibuat Pada')
                                    ->dateTime('d M Y H:i')
                                    ->icon('heroicon-m-pencil'),
                                
                                TextEntry::make('submittedBy.name')
                                    ->label('Dibuat Oleh')
                                    ->icon('heroicon-m-user'),
                                
                                TextEntry::make('updated_at')
                                    ->label('Diperbarui Pada')
                                    ->dateTime('d M Y H:i')
                                    ->icon('heroicon-m-arrow-path'),
                                
                                TextEntry::make('deleted_at')
                                    ->label('Dihapus Pada')
                                    ->dateTime('d M Y H:i')
                                    ->icon('heroicon-m-trash')
                                    ->visible(fn ($record) => $record->trashed()),
                            ]),
                    ])
                    ->collapsible(true)
                    ->collapsed(true),

               Section::make('Riwayat Aktivitas')
                    ->description('Log perubahan status dan data inspeksi')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        RepeatableEntry::make('activities')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Waktu')
                                            ->dateTime('d M Y H:i:s')
                                            ->size('sm'),
                                        
                                        TextEntry::make('causer.name')
                                            ->label('User')
                                            ->default('System')
                                            ->size('sm')
                                            ->badge()
                                            ->color('gray'),
                                        
                                        TextEntry::make('description')
                                            ->label('Aktivitas')
                                            ->badge()
                                            ->color(fn ($state) => match($state) {
                                                'Inspeksi baru dibuat' => 'success',
                                                'Data inspeksi diperbarui' => 'warning',
                                                'Inspeksi dihapus' => 'danger',
                                                'Inspeksi dipulihkan' => 'info',
                                                'inspektor_ditugaskan' => 'info',
                                                'inspektor_diubah' => 'warning',
                                                'Inspeksi dibatalkan' => 'danger',
                                                default => 'gray',
                                            })
                                            ->size('sm'),
                                        
                                        // STATUS LAMA - BARU
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('properties.old.status')
                                                    ->label('Status Lama')
                                                    ->badge()
                                                    ->color(fn ($state) => match($state) {
                                                        'draft' => 'gray',
                                                        'cancelled' => 'danger',
                                                        'completed' => 'success',
                                                        'on_the_way' => 'warning',
                                                        'arrived' => 'success',
                                                        'in_progress' => 'primary',
                                                        'pending' => 'warning',
                                                        'under_review' => 'info',
                                                        'approved' => 'success',
                                                        'rejected' => 'danger',
                                                        'revision' => 'warning',
                                                        default => 'gray',
                                                    })
                                                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                                
                                                TextEntry::make('properties.new.status')
                                                    ->label('Status Baru')
                                                    ->badge()
                                                    ->color(fn ($state) => match($state) {
                                                        'draft' => 'gray',
                                                        'cancelled' => 'danger',
                                                        'completed' => 'success',
                                                        'on_the_way' => 'warning',
                                                        'arrived' => 'success',
                                                        'in_progress' => 'primary',
                                                        'pending' => 'warning',
                                                        'under_review' => 'info',
                                                        'approved' => 'success',
                                                        'rejected' => 'danger',
                                                        'revision' => 'warning',
                                                        default => 'warning',
                                                    })
                                                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                            ])
                                            ->visible(fn ($state) => !empty($state))
                                            ->columnSpanFull(),
                                        
                                        // INSPEKTOR LAMA - BARU (DENGAN NAME)
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('properties.old.old_inspector_id')
                                                    ->label('Inspector Lama')
                                                    ->formatStateUsing(function ($state) {
                                                        if (!$state) return '-';
                                                        $user = \App\Models\User::find($state);
                                                        return $user?->name ?? 'User ID: ' . $state;
                                                    })
                                                    ->icon('heroicon-m-user')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                                
                                                TextEntry::make('properties.new.new_inspector_id')
                                                    ->label('Inspector Baru')
                                                    ->formatStateUsing(function ($state) {
                                                        if (!$state) return '-';
                                                        $user = \App\Models\User::find($state);
                                                        return $user?->name ?? 'User ID: ' . $state;
                                                    })
                                                    ->icon('heroicon-m-user')
                                                    ->badge()
                                                    ->color('success')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                            ])
                                            ->visible(fn ($record) => 
                                                !empty($record->properties['old']['old_inspector_id']) || 
                                                !empty($record->properties['new']['new_inspector_id'])
                                            )
                                            ->columnSpanFull(),
                                        
                                        // FALLBACK UNTUK FORMAT LAMA (inspector_id)
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('properties.old.inspector_id')
                                                    ->label('Inspector Lama')
                                                    ->formatStateUsing(function ($state) {
                                                        if (!$state) return '-';
                                                        $user = \App\Models\User::find($state);
                                                        return $user?->name ?? 'User ID: ' . $state;
                                                    })
                                                    ->icon('heroicon-m-user')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                                
                                                TextEntry::make('properties.new.inspector_id')
                                                    ->label('Inspector Baru')
                                                    ->formatStateUsing(function ($state) {
                                                        if (!$state) return '-';
                                                        $user = \App\Models\User::find($state);
                                                        return $user?->name ?? 'User ID: ' . $state;
                                                    })
                                                    ->icon('heroicon-m-user')
                                                    ->badge()
                                                    ->color('success')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                            ])
                                            ->visible(fn ($record) => 
                                                (empty($record->properties['old']['old_inspector_id']) && empty($record->properties['new']['new_inspector_id'])) &&
                                                (!empty($record->properties['old']['inspector_id']) || !empty($record->properties['new']['inspector_id']))
                                            )
                                            ->columnSpanFull(),
                                        
                                        // TANGGAL INSPEKSI LAMA - BARU
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('properties.old.old_date')
                                                    ->label('Tanggal Lama')
                                                    ->formatStateUsing(function ($state) {
                                                        if (!$state) return '-';
                                                        return \Carbon\Carbon::parse($state)->format('d M Y H:i');
                                                    })
                                                    ->icon('heroicon-m-calendar')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                                
                                                TextEntry::make('properties.new.new_date')
                                                    ->label('Tanggal Baru')
                                                    ->formatStateUsing(function ($state) {
                                                        if (!$state) return '-';
                                                        return \Carbon\Carbon::parse($state)->format('d M Y H:i');
                                                    })
                                                    ->icon('heroicon-m-calendar')
                                                    ->badge()
                                                    ->color('success')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                            ])
                                            ->visible(fn ($record) => 
                                                !empty($record->properties['old']['old_date']) || 
                                                !empty($record->properties['new']['new_date'])
                                            )
                                            ->columnSpanFull(),
                                        
                                        // FALLBACK UNTUK FORMAT LAMA (inspection_date)
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('properties.old.inspection_date')
                                                    ->label('Tanggal Lama')
                                                    ->formatStateUsing(function ($state) {
                                                        if (!$state) return '-';
                                                        return \Carbon\Carbon::parse($state)->format('d M Y H:i');
                                                    })
                                                    ->icon('heroicon-m-calendar')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                                
                                                TextEntry::make('properties.new.inspection_date')
                                                    ->label('Tanggal Baru')
                                                    ->formatStateUsing(function ($state) {
                                                        if (!$state) return '-';
                                                        return \Carbon\Carbon::parse($state)->format('d M Y H:i');
                                                    })
                                                    ->icon('heroicon-m-calendar')
                                                    ->badge()
                                                    ->color('success')
                                                    ->visible(fn ($state) => !empty($state))
                                                    ->size('sm'),
                                            ])
                                            ->visible(fn ($record) => 
                                                (empty($record->properties['old']['old_date']) && empty($record->properties['new']['new_date'])) &&
                                                (!empty($record->properties['old']['inspection_date']) || !empty($record->properties['new']['inspection_date']))
                                            )
                                            ->columnSpanFull(),
                                        
                                        // REGION
                                        TextEntry::make('properties.new.region_id')
                                            ->label('Region')
                                            ->formatStateUsing(function ($state) {
                                                if (!$state) return '-';
                                                $region = \App\Models\MasterData\Region::find($state);
                                                return $region?->name ?? 'Region ID: ' . $state;
                                            })
                                            ->icon('heroicon-m-map')
                                            ->badge()
                                            ->color('info')
                                            ->visible(fn ($state) => !empty($state))
                                            ->size('sm'),
                                        
                                        // ALASAN/CATATAN
                                        TextEntry::make('properties.reason')
                                            ->label('Keterangan')
                                            ->limit(100)
                                            ->tooltip(fn ($state) => $state)
                                            ->visible(fn ($state) => !empty($state))
                                            ->columnSpanFull()
                                            ->size('sm'),
                                        
                                        TextEntry::make('properties.notes')
                                            ->label('Catatan')
                                            ->limit(100)
                                            ->tooltip(fn ($state) => $state)
                                            ->visible(fn ($state) => !empty($state))
                                            ->columnSpanFull()
                                            ->size('sm'),
                                    ]),
                            ])
                            ->grid(1)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(true),
            ]);
    }
}