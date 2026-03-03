<?php

namespace App\Filament\Resources\Inspection\MasterData\CustomerResource\RelationManagers;

use App\Models\MasterData\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SellerRelationManager extends RelationManager
{
    protected static string $relationship = 'sellers';

    protected static ?string $recordTitleAttribute = 'unit_holder_name';
    
    protected static ?string $title = 'Daftar Seller';
    
    protected static ?string $modelLabel = 'Seller';
    
    protected static ?string $pluralModelLabel = 'Seller';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Data Relasi')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default($this->getOwnerRecord()->id)
                            ->disabled()
                            ->dehydrated(),
                    ]),
                
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

                
                Forms\Components\Section::make('Pengaturan & Status')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->label('Pengaturan Tambahan')
                            ->keyLabel('Kunci')
                            ->valueLabel('Nilai')
                            ->reorderable()
                            ->default([
                                'vehicle_type' => '',
                                'notes' => ''
                            ]),
                        
                        Forms\Components\Select::make('inspection_id')
                            ->label('Inspeksi')
                            ->relationship('inspection', 'id')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unit_holder_name')
            ->columns([
                Tables\Columns\TextColumn::make('unit_holder_name')
                    ->label('Pemegang Unit')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                
                Tables\Columns\TextColumn::make('unit_holder_phone')
                    ->label('No. HP Pemegang')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-phone'),
                
                Tables\Columns\TextColumn::make('inspection_area')
                    ->label('Area Inspeksi')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('link_maps')
                    ->label('Maps')
                    ->formatStateUsing(fn (string $state): string => '🔗 Buka Maps')
                    ->url(fn (string $state): string => $state)
                    ->openUrlInNewTab()
                    ->visible(fn ($record): bool => !empty($record->link_maps)),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['customer_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),

            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['customer_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}