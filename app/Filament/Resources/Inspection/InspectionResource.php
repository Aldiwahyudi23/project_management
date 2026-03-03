<?php

namespace App\Filament\Resources\Inspection;

use App\Filament\Resources\Inspection\InspectionResource\Pages;
use App\Filament\Resources\Inspection\InspectionResource\RelationManagers;
use App\Models\Inspection;
use App\Models\DirectDB\DirectDBInspection;
use App\Models\DirectDB\Vehicle;
use App\Models\DirectDB\Inspection\Template;
use App\Models\MasterData\Region;
use App\Models\RegionTeam;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InspectionResource extends Resource
{
    protected static ?string $model = Inspection::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    
    protected static ?string $navigationLabel = 'Inspeksi';
    
    protected static ?string $navigationGroup = 'Manajemen Inspeksi';
    
    protected static ?string $slug = 'inspections';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $recordTitleAttribute = 'reference';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    // Step 1: Data Kendaraan & Template
                    Forms\Components\Wizard\Step::make('Data Kendaraan')
                        ->icon('heroicon-o-truck')
                        ->schema([
                            Forms\Components\Section::make('Informasi Plat Nomor')
                                ->schema([
                                    Forms\Components\Grid::make(3)
                                        ->schema([
                                            Forms\Components\Select::make('license_plate_prefix')
                                                ->label('Plat Depan')
                                                ->options([
                                                    'D' => 'D (Bandung)',
                                                    'B' => 'B (Jakarta)',
                                                    'A' => 'A (Banten)',
                                                    'E' => 'E (Cirebon)',
                                                    'F' => 'F (Bogor)',
                                                    'T' => 'T (Purwakarta)',
                                                    'Z' => 'Z (Jawa Barat)',
                                                    'AB' => 'AB (Yogyakarta)',
                                                    'AD' => 'AD (Surakarta)',
                                                    'AE' => 'AE (Madiun)',
                                                    'AG' => 'AG (Kediri)',
                                                    'BA' => 'BA (Sumatera Barat)',
                                                    'BB' => 'BB (Sumatera Utara)',
                                                    'BK' => 'BK (Sumatera Utara)',
                                                    'BL' => 'BL (Aceh)',
                                                    'BM' => 'BM (Riau)',
                                                    'BN' => 'BN (Bangka Belitung)',
                                                    'BP' => 'BP (Kepulauan Riau)',
                                                    'DA' => 'DA (Kalimantan Selatan)',
                                                    'DB' => 'DB (Sulawesi Utara)',
                                                    'DC' => 'DC (Sulawesi Barat)',
                                                    'DD' => 'DD (Sulawesi Selatan)',
                                                    'DE' => 'DE (Maluku)',
                                                    'DG' => 'DG (Maluku Utara)',
                                                    'DH' => 'DH (NTT)',
                                                    'DK' => 'DK (Bali)',
                                                    'DL' => 'DL (Sulawesi Utara)',
                                                    'DM' => 'DM (Gorontalo)',
                                                    'DN' => 'DN (Sulawesi Tengah)',
                                                    'DP' => 'DP (Papua)',
                                                    'DR' => 'DR (NTB)',
                                                    'DT' => 'DT (Sulawesi Tenggara)',
                                                    'EA' => 'EA (NTB)',
                                                    'EB' => 'EB (NTT)',
                                                    'ED' => 'ED (Sulawesi Selatan)',
                                                    'G' => 'G (Pekalongan)',
                                                    'H' => 'H (Semarang)',
                                                    'K' => 'K (Pati)',
                                                    'KB' => 'KB (Kalimantan Barat)',
                                                    'KH' => 'KH (Kalimantan Tengah)',
                                                    'KT' => 'KT (Kalimantan Timur)',
                                                    'KU' => 'KU (Kalimantan Utara)',
                                                    'L' => 'L (Surabaya)',
                                                    'M' => 'M (Madura)',
                                                    'N' => 'N (Malang)',
                                                    'P' => 'P (Jember)',
                                                    'PA' => 'PA (Papua)',
                                                    'PB' => 'PB (Papua Barat)',
                                                    'R' => 'R (Banyumas)',
                                                    'S' => 'S (Bojonegoro)',
                                                    'W' => 'W (Sidoarjo)',
                                                ])
                                                ->searchable()
                                                ->required()
                                                ->columnSpan(1)
                                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                    if ($state) {
                                                        $prefix = $state;
                                                        $number = $get('license_plate_number');
                                                        $suffix = $get('license_plate_suffix');

                                                        if ($number && $suffix) {
                                                            $set('license_plate', $prefix .' '. $number .' '. $suffix);
                                                        }
                                                    }
                                                }),
                                            
                                            Forms\Components\TextInput::make('license_plate_number')
                                                ->label('Nomor Plat')
                                                ->required()
                                                ->maxLength(4)
                                                ->numeric()
                                                ->rule('digits_between:1,4')
                                                ->extraInputAttributes([
                                                    'maxlength' => 4,
                                                    'inputmode' => 'numeric',
                                                    'pattern' => '[0-9]*',
                                                    'oninput' => 'this.value = this.value.slice(0,4)'
                                                ])
                                                ->placeholder('1234')
                                                ->columnSpan(1)
                                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                    $prefix = $get('license_plate_prefix');
                                                    $suffix = $get('license_plate_suffix');

                                                    if ($prefix && $state && $suffix) {
                                                        $set('license_plate', $prefix .' ' . $state .' '  . $suffix);
                                                    }
                                                }),
                                            
                                            Forms\Components\TextInput::make('license_plate_suffix')
                                                ->label('Plat Belakang')
                                                ->required()
                                                ->maxLength(3)
                                                ->rule('regex:/^[A-Z]{1,3}$/')
                                                ->extraInputAttributes([
                                                    'maxlength' => 3,
                                                    'style' => 'text-transform: uppercase;',
                                                    'oninput' => 'this.value = this.value.toUpperCase().replace(/[^A-Z]/g, "").slice(0,3)'
                                                ])
                                                ->placeholder('ABC')
                                                ->columnSpan(1)
                                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                    $state = strtoupper($state);
                                                    $set('license_plate_suffix', $state);

                                                    $prefix = $get('license_plate_prefix');
                                                    $number = $get('license_plate_number');

                                                    if ($prefix && $number && $state) {
                                                        $set('license_plate', $prefix .' ' . $number .' ' . $state);
                                                    }
                                                }),
                                        ]),
                                    
                                    Forms\Components\Hidden::make('license_plate'),
                                ]),
                            
                            Forms\Components\Section::make('Pilih Kendaraan')
                                ->schema([
                                    Forms\Components\Select::make('brand_id')
                                        ->label('Brand')
                                        ->options(fn () => self::getVehicleOptions('brand_id', 'brand.name'))
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(fn (Set $set) => self::resetVehicleFields($set, 'brand_id'))
                                        ->required(),

                                    Forms\Components\Select::make('model_id')
                                        ->label('Model')
                                        ->options(fn (Get $get) => self::getVehicleOptions('model_id', 'model.name', $get))
                                        ->searchable()
                                        ->live()
                                        ->disabled(fn (Get $get) => !$get('brand_id'))
                                        ->afterStateUpdated(fn (Set $set) => self::resetVehicleFields($set, 'model_id'))
                                        ->required(),

                                    Forms\Components\Select::make('type_id')
                                        ->label('Type')
                                        ->options(fn (Get $get) => self::getVehicleOptions('type_id', 'type.name', $get))
                                        ->searchable()
                                        ->live()
                                        ->disabled(fn (Get $get) => !$get('model_id'))
                                        ->afterStateUpdated(fn (Set $set) => self::resetVehicleFields($set, 'type_id'))
                                        ->required(),

                                    Forms\Components\Select::make('year')
                                        ->label('Year')
                                        ->options(fn (Get $get) => self::getVehicleOptions('year', null, $get))
                                        ->searchable()
                                        ->live()
                                        ->disabled(fn (Get $get) => !$get('type_id'))
                                        ->afterStateUpdated(fn (Set $set) => self::resetVehicleFields($set, 'year'))
                                        ->required(),

                                    Forms\Components\Select::make('cc')
                                        ->label('CC')
                                        ->options(fn (Get $get) => self::getVehicleOptions(
                                            'cc',
                                            null,
                                            $get,
                                            fn ($value) => $value ? number_format($value / 1000, 1) . 'L' : ''
                                        ))
                                        ->searchable()
                                        ->live()
                                        ->disabled(fn (Get $get) => !$get('year'))
                                        ->afterStateUpdated(fn (Set $set) => self::resetVehicleFields($set, 'cc'))
                                        ->required(),

                                    Forms\Components\Radio::make('transmission_id')
                                        ->label('Transmission')
                                        ->options(fn (Get $get) => self::getVehicleOptions('transmission_id', 'transmission.code', $get))
                                        ->live()
                                        ->disabled(fn (Get $get) => !$get('cc'))
                                        ->afterStateUpdated(fn (Set $set) => self::resetVehicleFields($set, 'transmission_id'))
                                        ->columns(2)
                                        ->required(),

                                    Forms\Components\Select::make('fuel_type')
                                        ->label('Fuel Type')
                                        ->options(fn (Get $get) => self::getVehicleOptions('fuel_type', null, $get))
                                        ->searchable()
                                        ->live()
                                        ->disabled(fn (Get $get) => !$get('transmission_id'))
                                        ->afterStateUpdated(fn (Set $set) => self::resetVehicleFields($set, 'fuel_type'))
                                        ->required(),

                                    Forms\Components\Select::make('market_period')
                                        ->label('Market Period')
                                        ->options(fn (Get $get) => self::getVehicleOptions('market_period', null, $get))
                                        ->searchable()
                                        ->disabled(fn (Get $get) => !$get('fuel_type'))
                                        ->required(),
                                ])
                                ->columns(2),
                            
                            Forms\Components\Section::make('Template Inspeksi')
                                ->schema([
                                    Forms\Components\Select::make('template_id')
                                        ->label('Pilih Template Inspeksi')
                                        ->options(function () {
                                            return \App\Models\DirectDB\Inspection\Template::where('is_active', true)
                                                ->whereNotNull('name')
                                                ->pluck('name', 'id')
                                                ->toArray() ?? [];
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->helperText('Halaman yang akan digunakan untuk inspeksi ini'),
                                ]),
                        ]),
                    
                    // Step 2: Data Customer & Seller
                    Forms\Components\Wizard\Step::make('Customer & Seller')
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            Forms\Components\Section::make('Data Customer')
                                ->schema([
                                    Forms\Components\Select::make('customer_id')
                                        ->label('Pilih Customer')
                                        ->relationship('customer', 'name')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            Forms\Components\Section::make('Data Identitas Customer')
                                                ->schema([
                                                    Forms\Components\TextInput::make('name')
                                                        ->label('Nama Customer')
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->placeholder('Masukkan nama customer'),

                                                    Forms\Components\TextInput::make('phone')
                                                        ->label('Nomor WhatsApp')
                                                        ->tel()
                                                        ->required()
                                                        ->numeric()
                                                        ->minLength(9)
                                                        ->maxLength(13)
                                                        ->prefix('+62')
                                                        ->unique(ignoreRecord: true)
                                                        ->rule('regex:/^[0-9]{9,13}$/')
                                                        ->validationMessages([
                                                            'regex' => 'Nomor HP harus 9–13 digit angka.',
                                                        ])
                                                        ->placeholder('81234567890'),

                                                    Forms\Components\TextInput::make('email')
                                                        ->label('Email')
                                                        ->email()
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->unique(ignoreRecord: true)
                                                        ->placeholder('customer@example.com'),
                                                ])
                                                ->columns(2),

                                            Forms\Components\Section::make('Data Alamat Customer')
                                                ->schema([
                                                    Forms\Components\Textarea::make('address')
                                                        ->label('Alamat Lengkap')
                                                        ->rows(3)
                                                        ->maxLength(65535)
                                                        ->placeholder('Masukkan alamat lengkap customer'),
                                                ]),

                                        ])
                                        ->createOptionUsing(function (array $data): int {
                                            return \App\Models\MasterData\Customer\Customer::create([
                                                'name' => $data['name'],
                                                'phone' => $data['phone'] ?? null,
                                                'email' => $data['email'] ?? null,
                                                'address' => $data['address'] ?? null,
                                            ])->id;
                                        }),
                                    
                                    Forms\Components\TextInput::make('reference')
                                        ->label('Referensi / Nomor Dokumen')
                                        ->maxLength(255)
                                        ->placeholder('Masukkan nomor referensi jika ada')
                                        ->nullable(),
                                ]),
                            
                            Forms\Components\Section::make('Data Seller')
                                ->schema([
                                    Forms\Components\Repeater::make('sellers')
                                        ->relationship('sellers')
                                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Forms\Get $get): array {
                                            $data['customer_id'] = $get('customer_id');
                                            return $data;
                                        })
                                        ->schema([
                                            Forms\Components\Grid::make(2)
                                                ->schema([
                                                        Forms\Components\Section::make('Data Lokasi Inspeksi')
                                                        ->schema([

                                                            Forms\Components\Select::make('inspection_area')
                                                                ->label('Area Inspeksi')
                                                                ->options(
                                                                    Region::where('is_active', 1)
                                                                        ->orderBy('name')
                                                                        ->pluck('name', 'name')
                                                                )
                                                                ->searchable()
                                                                ->preload()
                                                                ->required()
                                                                ->placeholder('Pilih area inspeksi'),

                                                            Forms\Components\Textarea::make('inspection_address')
                                                                ->label('Alamat Inspeksi')
                                                                ->rows(3)
                                                                ->maxLength(65535)
                                                                ->placeholder('Masukkan alamat lengkap inspeksi'),

                                                            Forms\Components\TextInput::make('link_maps')
                                                                ->label('Link Google Maps')
                                                                ->url()
                                                                ->required()
                                                                ->rule('active_url')
                                                                ->maxLength(65535)
                                                                ->prefix('🔗')
                                                                ->placeholder('https://maps.google.com/...'),

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

                                                        ])
                                                        ->columns(2),
                                                ]),
                                        ])
                                        ->defaultItems(0)
                                        ->maxItems(5)
                                        ->collapsible()
                                        ->collapsed(false)
                                        ->itemLabel(fn (array $state): ?string => 
                                            $state['unit_holder_name'] ?? 'Seller Baru'
                                        ),
                                ]),
                        ]),
                    
                    // Step 3: Penugasan Inspector
                    Forms\Components\Wizard\Step::make('Penugasan')
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            Forms\Components\Section::make('Pilih Region & Inspector')
                                ->schema([
                                    Forms\Components\Select::make('region_id')
                                        ->label('Region')
                                        ->options(function () {
                                            return Region::where('is_active', true)
                                                ->whereNotNull('name')
                                                ->pluck('name', 'id')
                                                ->toArray() ?? [];
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn (Set $set) => $set('inspector_id', null))
                                        ->helperText('Pilih region untuk menampilkan inspector yang tersedia'),
                                    
                                    Forms\Components\Select::make('inspector_id')
                                        ->label('Inspector')
                                        ->options(function (Get $get) {
                                            $regionId = $get('region_id');
                                            if (!$regionId) {
                                                return [];
                                            }
                                            
                                            // Ambil user_id dari region_team yang memiliki role inspector
                                            // User bisa memiliki multiple roles
                                            return User::whereHas('regionTeams', function ($query) use ($regionId) {
                                                    $query->where('region_id', $regionId);
                                                })
                                                ->whereHas('roles', function ($query) {
                                                    $query->where('name', 'inspector'); // Role name untuk inspector
                                                })
                                                ->whereNotNull('name')
                                                ->pluck('name', 'id')
                                                ->toArray() ?? [];
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->disabled(fn (Get $get): bool => !$get('region_id'))
                                        ->helperText('Pilih inspector yang akan ditugaskan'),
                                    
                                    Forms\Components\DateTimePicker::make('inspection_date')
                                        ->label('Tanggal & Waktu Inspeksi')
                                        ->required()
                                        ->seconds(false)
                                        ->displayFormat('d M Y H:i')
                                        ->minDate(now())
                                        ->maxDate(now()->addMonths(3))
                                        ->default(now()->addDay()->setTime(9, 0))
                                        ->helperText('Jadwalkan waktu pelaksanaan inspeksi'),
                                ]),
                        ]),
                    
                    // Step 4: Konfirmasi & Submit - FIXED VEHICLE SUMMARY
                    Forms\Components\Wizard\Step::make('Konfirmasi')
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Forms\Components\Section::make('Ringkasan Data Kendaraan')
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Placeholder::make('summary_license_plate')
                                                ->label('Plat Nomor')
                                                ->content(function (Get $get) {
                                                    return $get('license_plate') ?? '-';
                                                }),
                                            
                                            Forms\Components\Placeholder::make('summary_vehicle_full')
                                                ->label('Spesifikasi Kendaraan')
                                                ->content(function (Get $get) {
                                                    // Ambil data dari form fields yang benar
                                                    $brandId = $get('brand_id');
                                                    $modelId = $get('model_id');
                                                    $typeId = $get('type_id');
                                                    $cc = $get('cc');
                                                    $year = $get('year');
                                                    $transmissionId = $get('transmission_id');
                                                    $fuelType = $get('fuel_type');
                                                    $marketPeriod = $get('market_period');
                                                    
                                                    // Ambil data dari database untuk label
                                                    $brand = $brandId ? \App\Models\DirectDB\VehicleData\Brand::find($brandId)?->name : null;
                                                    $model = $modelId ? \App\Models\DirectDB\VehicleData\VehicleModel::find($modelId)?->name : null;
                                                    $type = $typeId ? \App\Models\DirectDB\VehicleData\VehicleType::find($typeId)?->name : null;
                                                    
                                                    // Format CC ke liter
                                                    $ccFormatted = $cc ? number_format($cc / 1000, 1) . 'L' : null;
                                                    
                                                    // Ambil transmission
                                                    $transmission = null;
                                                    if ($transmissionId) {
                                                        $transmissionModel = \App\Models\DirectDB\VehicleData\Transmission::find($transmissionId);
                                                        $transmission = $transmissionModel?->name;
                                                        if ($transmissionModel?->code && $transmissionModel->code !== $transmissionModel->name) {
                                                            $transmission .= ' (' . $transmissionModel->code . ')';
                                                        }
                                                    }
                                                    
                                                    // Fuel Type mapping
                                                    $fuelLabels = [
                                                        'bensin' => 'Bensin',
                                                        'diesel' => 'Diesel',
                                                        'hybrid' => 'Hybrid',
                                                        'electric' => 'Electric',
                                                    ];
                                                    $fuel = $fuelLabels[$fuelType] ?? $fuelType;
                                                    
                                                    // Format market period
                                                    $period = $marketPeriod;
                                                    
                                                    // Build array komponen kendaraan
                                                    $vehicleParts = [];
                                                    if ($brand) $vehicleParts[] = $brand;
                                                    if ($model) $vehicleParts[] = $model;
                                                    if ($type) $vehicleParts[] = $type;
                                                    if ($ccFormatted) $vehicleParts[] = $ccFormatted;
                                                    if ($year) $vehicleParts[] = $year;
                                                    if ($transmission) $vehicleParts[] = $transmission;
                                                    if ($fuel) $vehicleParts[] = $fuel;
                                                    if ($period) $vehicleParts[] = 'Periode: ' . $period;
                                                    
                                                    return !empty($vehicleParts) ? implode(' • ', $vehicleParts) : '-';
                                                })
                                                ->columnSpanFull()
                                                ->extraAttributes([
                                                    'class' => 'text-base font-medium bg-gray-50 p-3 rounded-lg border border-gray-200 dark:bg-gray-800 dark:border-gray-700 dark:text-white',
                                                ]),
                                        ]),
                                ]),
                            
                            Forms\Components\Section::make('Ringkasan Inspeksi')
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Placeholder::make('summary_template')
                                                ->label('Template')
                                                ->content(function (Get $get) {
                                                    $templateId = $get('template_id');
                                                    return $templateId 
                                                        ? \App\Models\DirectDB\Inspection\Template::find($templateId)?->name ?? '-' 
                                                        : '-';
                                                }),
                                            
                                            Forms\Components\Placeholder::make('summary_customer')
                                                ->label('Customer')
                                                ->content(function (Get $get) {
                                                    $customerId = $get('customer_id');
                                                    return $customerId 
                                                        ? \App\Models\MasterData\Customer\Customer::find($customerId)?->name ?? '-' 
                                                        : '-';
                                                }),
                                            
                                            Forms\Components\Placeholder::make('summary_inspector')
                                                ->label('Inspector & Jadwal')
                                                ->content(function (Get $get) {
                                                    $inspectorId = $get('inspector_id');
                                                    $inspector = $inspectorId ? User::find($inspectorId)?->name : '';
                                                    $date = $get('inspection_date') 
                                                        ? \Carbon\Carbon::parse($get('inspection_date'))->format('d M Y H:i')
                                                        : '';
                                                    
                                                    if ($inspector && $date) {
                                                        return $inspector . ' • ' . $date;
                                                    }
                                                    return trim(($inspector ?? '-') . ' - ' . ($date ?? '-'));
                                                })
                                                ->columnSpanFull(),
                                        ]),
                                ]),
                            
                            Forms\Components\Section::make('Konfirmasi')
                                ->schema([
                                    Forms\Components\Checkbox::make('confirm_submit')
                                        ->label('Saya telah memeriksa dan menyatakan bahwa data yang diisi sudah benar')
                                        ->required()
                                        ->accepted(),
                                ]),
                        ]),
                ])
                ->columnSpanFull()
                ->skippable()
                ->persistStepInQueryString(),
            ]);
    }

    // Fungsi untuk handle submit ke 2 database dengan pengecekan null
    public static function afterCreate($record, array $data): void
    {
        try {
            DB::transaction(function () use ($record, $data) {
                // === CARI VEHICLE ID BERDASARKAN KRITERIA ===
                $vehicle = self::findOrCreateVehicle($data);
                $vehicleId = $vehicle ? $vehicle->id : null;
                $vehicleName = $vehicle ? self::formatVehicleName($vehicle) : self::formatVehicleNameFromData($data);
                
                // Siapkan data untuk eksternal dengan filter null
                $externalData = [
                    'template_id' => $data['template_id'] ?? null,
                    'vehicle_id' => $vehicleId, // ID dari tabel vehicles
                    'vehicle_name' => $vehicleName, // Nama kendaraan yang diformat
                    'license_plate' => $data['license_plate'] ?? null,
                    'mileage' => 0,
                    'inspection_date' => $data['inspection_date'] ?? now(),
                    'status' => 'draft',
                    'inspection_code' => 'INS-' . strtoupper(Str::random(8)),
                ];

                // Hapus key dengan nilai null
                $externalData = array_filter($externalData, function ($value) {
                    return !is_null($value);
                });

                // 1. Simpan ke database eksternal (DirectDBInspection)
                $externalInspection = DirectDBInspection::create($externalData);

                // 2. Update record lokal dengan ID dari eksternal
                $externalDataArray = [
                    'vehicle_id' => $vehicleId,
                    'vehicle_name' => $vehicleName,
                    'brand_id' => $data['brand_id'] ?? null,
                    'model_id' => $data['model_id'] ?? null,
                    'type_id' => $data['type_id'] ?? null,
                    'template_id' => $data['template_id'] ?? null,
                    'license_plate' => $data['license_plate'] ?? null,
                    'cc' => $data['cc'] ?? null,
                    'year' => $data['year'] ?? null,
                    'transmission_id' => $data['transmission_id'] ?? null,
                    'fuel_type' => $data['fuel_type'] ?? null,
                    'market_period' => $data['market_period'] ?? null,
                ];
                
                // Filter null dari external_data
                $externalDataArray = array_filter($externalDataArray, function ($value) {
                    return !is_null($value);
                });


                // =====================
                // FORMAT VEHICLE NAME & LICENSE PLATE
                // =====================

                $formattedVehicleName = $vehicleName;

                $formattedLicensePlate = $data['license_plate'] ?? null;

                // =====================
                // BUILD LOCAL SETTINGS JSON
                // =====================

                $localSettings = [
                    'vehicle' => [
                        'brand_id' => $data['brand_id'] ?? null,
                        'model_id' => $data['model_id'] ?? null,
                        'type_id' => $data['type_id'] ?? null,
                        'cc' => $data['cc'] ?? null,
                        'year' => $data['year'] ?? null,
                        'transmission_id' => $data['transmission_id'] ?? null,
                        'fuel_type' => $data['fuel_type'] ?? null,
                        'market_period' => $data['market_period'] ?? null,
                    ],
                    'vehicle_name' => $formattedVehicleName,
                    'license_plate' => $formattedLicensePlate,
                ];

                // filter null dalam nested vehicle
                $localSettings['vehicle'] = array_filter(
                    $localSettings['vehicle'],
                    fn($v) => !is_null($v)
                );


                $record->update([
                    'inspection_id' => $externalInspection->id,
                    'direct_db_inspection_id' => $externalInspection->id,
                    'external_data' => !empty($externalDataArray) ? $externalDataArray : null,
                    'settings' => $localSettings,
                ]);
                // =============================
                // 3. Update seller records dengan inspection_id
                if (isset($data['sellers']) && is_array($data['sellers'])) {
                    foreach ($record->sellers as $index => $seller) {
                        $sellerData = [
                            'inspection_id' => $record->id,
                            'customer_id' => $record->customer_id,
                        ];
                        
                        // Update jika ada data dari form
                        if (isset($data['sellers'][$index])) {
                            $sellerData = array_merge($sellerData, array_filter($data['sellers'][$index], function ($value) {
                                return !is_null($value);
                            }));
                        }
                        
                        $seller->update($sellerData);
                    }
                }
            });
        } catch (\Exception $e) {
            // Log error
            \Illuminate\Support\Facades\Log::error('Error creating external inspection: ' . $e->getMessage(), [
                'record_id' => $record->id,
                'data' => $data
            ]);
            
            // Jangan throw exception, biarkan record lokal tetap tersimpan
            session()->flash('warning', 'Inspeksi tersimpan di database lokal, namun gagal sinkron ke database eksternal: ' . $e->getMessage());
        }
    }

    public static function afterUpdate($record, array $data): void
    {
        try {
            DB::transaction(function () use ($record, $data) {
                // Update data eksternal jika ada perubahan
                if ($record->externalInspection) {
                    // Cari atau update vehicle
                    $vehicle = self::findOrCreateVehicle($data);
                    $vehicleId = $vehicle ? $vehicle->id : null;
                    $vehicleName = $vehicle ? self::formatVehicleName($vehicle) : self::formatVehicleNameFromData($data);
                    
                    $updateData = [];
                    
                    if (isset($data['template_id'])) {
                        $updateData['template_id'] = $data['template_id'];
                    }
                    
                    if ($vehicleId) {
                        $updateData['vehicle_id'] = $vehicleId;
                        $updateData['vehicle_name'] = $vehicleName;
                    }
                    
                    if (isset($data['license_plate'])) {
                        $updateData['license_plate'] = $data['license_plate'];
                    }
                    
                    if (isset($data['inspection_date'])) {
                        $updateData['inspection_date'] = $data['inspection_date'];
                    }
                    
                    
                    // Filter null
                    $updateData = array_filter($updateData, function ($value) {
                        return !is_null($value);
                    });
                    
                    if (!empty($updateData)) {
                        $record->externalInspection->update($updateData);
                    }
                }
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating external inspection: ' . $e->getMessage(), [
                'record_id' => $record->id,
                'data' => $data
            ]);
            
            session()->flash('warning', 'Data lokal tersimpan, namun gagal sinkron ke database eksternal: ' . $e->getMessage());
        }
    }

    /**
     * Find or create vehicle based on selected criteria
     */
    protected static function findOrCreateVehicle(array $data): ?\App\Models\DirectDB\Vehicle
    {
        if (empty($data['brand_id']) || empty($data['model_id']) || empty($data['type_id'])) {
            return null;
        }
        
        // Cari vehicle berdasarkan kriteria
        $query = Vehicle::where('brand_id', $data['brand_id'])
            ->where('model_id', $data['model_id'])
            ->where('type_id', $data['type_id']);
        
        if (!empty($data['year'])) {
            $query->where('year', $data['year']);
        }
        
        
        if (!empty($data['transmission_id'])) {
            $query->where('transmission_id', $data['transmission_id']);
        }

        if (!empty($data['cc'])) {
            $query->where('cc', $data['cc']);
        }
        
        if (!empty($data['fuel_type'])) {
            $query->where('fuel_type', $data['fuel_type']);
        }
        
        if (!empty($data['market_period'])) {
            $query->where('market_period', $data['market_period']);
        }
        
        $vehicle = $query->first();
        
        // Jika tidak ditemukan, buat baru
        if (!$vehicle) {
            $vehicle = Vehicle::create([
                'brand_id' => $data['brand_id'],
                'model_id' => $data['model_id'],
                'type_id' => $data['type_id'],
                'cc' => $data['cc'] ?? null,
                'year' => $data['year'] ?? null,
                'transmission_id' => $data['transmission_id'] ?? null,
                'fuel_type' => $data['fuel_type'] ?? null,
                'market_period' => $data['market_period'] ?? null,
                'is_active' => true,
            ]);
        }
        
        return $vehicle;
    }

    /**
     * Format vehicle name from vehicle model
     */
    protected static function formatVehicleName($vehicle): string
    {
        $parts = [];
        
        if ($vehicle->brand) {
            $parts[] = $vehicle->brand->name;
        }
        
        if ($vehicle->model) {
            $parts[] = $vehicle->model->name;
        }
        
        if ($vehicle->type) {
            $parts[] = $vehicle->type->name;
        }
        
        
        if ($vehicle->cc) {
            $parts[] = number_format($vehicle->cc / 1000, 1) . 'L';
            }
            
        if ($vehicle->year) {
            $parts[] = $vehicle->year;
        }

        if ($vehicle->transmission) {
            $transmission = $vehicle->transmission->name;
            if ($vehicle->transmission->code && $vehicle->transmission->code !== $vehicle->transmission->name) {
                $transmission .= ' (' . $vehicle->transmission->code . ')';
            }
            $parts[] = $transmission;
        }
        
        $fuelLabels = [
            'bensin' => 'Bensin',
            'diesel' => 'Diesel',
            'hybrid' => 'Hybrid',
            'electric' => 'Electric',
        ];
        
        if ($vehicle->fuel_type) {
            $parts[] = $fuelLabels[$vehicle->fuel_type] ?? $vehicle->fuel_type;
        }
        
        return implode(' ', $parts);
    }

    /**
     * Format vehicle name from form data (fallback)
     */
    protected static function formatVehicleNameFromData(array $data): string
    {
        $parts = [];
        
        if (!empty($data['brand_id'])) {
            $brand = \App\Models\DirectDB\VehicleData\Brand::find($data['brand_id']);
            if ($brand) $parts[] = $brand->name;
        }
        
        if (!empty($data['model_id'])) {
            $model = \App\Models\DirectDB\VehicleData\VehicleModel::find($data['model_id']);
            if ($model) $parts[] = $model->name;
        }
        
        if (!empty($data['type_id'])) {
            $type = \App\Models\DirectDB\VehicleData\VehicleType::find($data['type_id']);
            if ($type) $parts[] = $type->name;
        }
        
        if (!empty($data['year'])) {
            $parts[] = $data['year'];
        }
        
        if (!empty($data['cc'])) {
            $parts[] = number_format($data['cc'] / 1000, 1) . 'L';
        }
        
        if (!empty($data['transmission_id'])) {
            $transmission = \App\Models\DirectDB\VehicleData\Transmission::find($data['transmission_id']);
            if ($transmission) {
                $transmissionName = $transmission->name;
                if ($transmission->code && $transmission->code !== $transmission->name) {
                    $transmissionName .= ' (' . $transmission->code . ')';
                }
                $parts[] = $transmissionName;
            }
        }
        
        $fuelLabels = [
            'bensin' => 'Bensin',
            'diesel' => 'Diesel',
            'hybrid' => 'Hybrid',
            'electric' => 'Electric',
        ];
        
        if (!empty($data['fuel_type'])) {
            $parts[] = $fuelLabels[$data['fuel_type']] ?? $data['fuel_type'];
        }
        
        return implode(' ', $parts);
    }

    protected static function vehicleBaseQuery(?Get $get = null, ?string $except = null)
    {
        $query = Vehicle::query()
            ->where('is_active', true);
        
        // Brand - selalu filter kecuali sedang loading brand options
        if ($except !== 'brand_id' && $get && $get('brand_id')) {
            $query->where('brand_id', $get('brand_id'));
        }

        // Model - selalu filter kecuali sedang loading model options
        if ($except !== 'model_id' && $get && $get('model_id')) {
            $query->where('model_id', $get('model_id'));
        }

        // Type - selalu filter kecuali sedang loading type options
        if ($except !== 'type_id' && $get && $get('type_id')) {
            $query->where('type_id', $get('type_id'));
        }

        // Year - filter jika year sudah dipilih DAN tidak sedang dalam proses loading year
        if ($get && $get('year') && $except !== 'year') {
            $query->where('year', $get('year'));
        }

        // CC - filter jika cc sudah dipilih DAN tidak sedang dalam proses loading cc
        if ($get && $get('cc') && $except !== 'cc') {
            $query->where('cc', $get('cc'));
        }

        // Transmission - filter jika transmission sudah dipilih DAN tidak sedang dalam proses loading transmission
        if ($get && $get('transmission_id') && $except !== 'transmission_id') {
            $query->where('transmission_id', $get('transmission_id'));
        }

        // Fuel Type - filter jika fuel type sudah dipilih DAN tidak sedang dalam proses loading fuel type
        if ($get && $get('fuel_type') && $except !== 'fuel_type') {
            $query->where('fuel_type', $get('fuel_type'));
        }

        return $query;
    }

    protected static function getVehicleOptions(
        string $column,
        ?string $relationColumn = null,
        ?Get $get = null,
        ?callable $formatter = null
    ): array {
        
        $query = self::vehicleBaseQuery($get, $column)
            ->with(['brand', 'model', 'type', 'transmission'])
            ->whereNotNull($column)
            ->orderBy($column);

        return $query
            ->get()
            ->unique($column)
            ->mapWithKeys(function ($vehicle) use ($column, $relationColumn, $formatter) {
                
                $value = $vehicle->{$column};
                
                if ($value === null || $value === '') {
                    return [];
                }
                
                // Handle khusus untuk transmission
                if ($column === 'transmission_id' && $vehicle->relationLoaded('transmission') && $vehicle->transmission) {
                    $transmission = $vehicle->transmission;
                    $label = $transmission->name;
                    
                    // Tambahkan description jika ada
                    if (!empty($transmission->description)) {
                        $label .= ' - ' . $transmission->description;
                    }
                    
                    // Tambahkan code dalam tanda kurung
                    if (!empty($transmission->code) && $transmission->code !== $transmission->name) {
                        $label .= ' (' . $transmission->code . ')';
                    }
                    
                    return [$value => $label];
                }
                
                // Default handling untuk field lain
                $label = $relationColumn
                    ? data_get($vehicle, $relationColumn, $value)
                    : $value;
                
                if ($formatter) {
                    $label = $formatter($value);
                }
                
                return [$value => $label];
            })
            ->filter()
            ->toArray();
    }

    protected static function resetVehicleFields(Set $set, string $changedField): void
    {
        // Definisikan dependensi antar field
        $dependencies = [
            'brand_id' => ['model_id', 'type_id', 'year', 'cc', 'transmission_id', 'fuel_type', 'market_period'],
            'model_id' => ['type_id', 'year', 'cc', 'transmission_id', 'fuel_type', 'market_period'],
            'type_id' => ['year', 'cc', 'transmission_id', 'fuel_type', 'market_period'],
            'year' => ['cc', 'transmission_id', 'fuel_type', 'market_period'],
            'cc' => ['transmission_id', 'fuel_type', 'market_period'],
            'transmission_id' => ['fuel_type', 'market_period'],
            'fuel_type' => ['market_period'],
        ];

        // Reset hanya field yang dependen terhadap field yang berubah
        if (isset($dependencies[$changedField])) {
            foreach ($dependencies[$changedField] as $field) {
                $set($field, null);
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('direct_db_inspection_id')
                    ->label('ID Eksternal')
                    ->formatStateUsing(fn ($state): string => $state ? "#EXT-{$state}" : '-')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('externalInspection.license_plate')
                    ->label('Plat Nomor')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->formatStateUsing(fn ($state): string => $state ?? '-'),
                
                Tables\Columns\TextColumn::make('externalInspection.vehicle_name')
                    ->label('Kendaraan')
                    ->searchable()
                    ->limit(50)
                    ->formatStateUsing(fn ($state): string => $state ?? '-'),
                
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state ?? '-'),
                
                Tables\Columns\TextColumn::make('inspector.name')
                    ->label('Inspector')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state ?: 'Belum ditugaskan'),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Inspection $record): string => $record->status_color ?? 'gray')
                    ->formatStateUsing(fn (Inspection $record): string => $record->status_label ?? $record->status ?? '-'),
                
                Tables\Columns\TextColumn::make('inspection_date')
                    ->label('Tanggal Inspeksi')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state ? \Carbon\Carbon::parse($state)->format('d M Y H:i') : '-'),
                
                Tables\Columns\TextColumn::make('externalInspection.template.name')
                    ->label('Template')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state): string => $state ?? '-'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        'draft' => 'Draft',
                        'accepted' => 'Diterima',
                        'on_the_way' => 'Menuju Lokasi',
                        'arrived' => 'Sampai Lokasi',
                        'in_progress' => 'Sedang Berjalan',
                        'pending' => 'Tertunda',
                        'under_review' => 'Dalam Review',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        'revision' => 'Perlu Revisi',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ]),
                Tables\Filters\SelectFilter::make('inspector_id')
                    ->label('Inspector')
                    ->relationship('inspector', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\SellersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInspections::route('/'),
            'create' => Pages\CreateInspection::route('/create'),
            'view' => Pages\ViewInspection::route('/{record}'),
            // 'edit' => Pages\EditInspection::route('/{record}/edit'),
        ];
    }

    // public static function getEloquentQuery(): Builder
    // {
    //     return parent::getEloquentQuery()
    //         ->withoutGlobalScopes([
    //             SoftDeletingScope::class,
    //         ]);
    // }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'draft')->count() ?: null;
    }
}