<?php

namespace App\Filament\Resources\Inspection\InspectionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SellersRelationManager extends RelationManager
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
                            ->disabled()
                            ->dehydrated(),
                        
                        Forms\Components\Select::make('inspection_id')
                            ->label('Inspeksi')
                            ->relationship('inspection', 'reference')
                            ->searchable()
                            ->preload()
                            ->default($this->getOwnerRecord()->id)
                            ->disabled()
                            ->dehydrated(),
                    ]),
                
                Forms\Components\Section::make('Data Lokasi Inspeksi')
                    ->schema([
                        Forms\Components\TextInput::make('inspection_area')
                            ->label('Area Inspeksi')
                            ->maxLength(255),
                        
                        Forms\Components\Textarea::make('inspection_address')
                            ->label('Alamat Inspeksi')
                            ->rows(2)
                            ->maxLength(65535),
                        
                        Forms\Components\TextInput::make('link_maps')
                            ->label('Link Google Maps')
                            ->url()
                            ->maxLength(65535)
                            ->prefix('🔗'),
                    ]),
                
                Forms\Components\Section::make('Data Pemegang Unit')
                    ->schema([
                        Forms\Components\TextInput::make('unit_holder_name')
                            ->label('Nama Pemegang Unit')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('unit_holder_phone')
                            ->label('No. HP Pemegang Unit')
                            ->tel()
                            ->maxLength(20)
                            ->prefix('+62'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Seller')
                            ->options([
                                'submitted' => 'Submitted',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('submitted')
                            ->required(),
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
                    ->label('No. HP')
                    ->searchable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('inspection_area')
                    ->label('Area')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'submitted' => 'info',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['inspection_id'] = $this->getOwnerRecord()->id;
                        $data['customer_id'] = $this->getOwnerRecord()->customer_id;
                        return $data;
                    }),
                
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['unit_holder_name', 'inspection_area'])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['inspection_id'] = $this->getOwnerRecord()->id;
                        $data['customer_id'] = $this->getOwnerRecord()->customer_id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}