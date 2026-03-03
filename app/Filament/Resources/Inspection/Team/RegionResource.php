<?php

namespace App\Filament\Resources\Inspection\Team;
use App\Filament\Resources\Inspection\Team\RegionResource\Pages;
use App\Filament\Resources\Team\RegionResource\RelationManagers;
use App\Filament\Resources\Inspection\Team\RegionResource\RelationManagers\RegionTeamRelationManager;
use App\Models\MasterData\Region;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    
    // Label navigasi
    protected static ?string $navigationGroup = 'User Management';

    protected static ?string $navigationLabel = 'Regions';
    
    // Nama tunggal dan jamak
    protected static ?string $modelLabel = 'Region';
    protected static ?string $pluralModelLabel = 'Regions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                 Forms\Components\Section::make('Informasi Region')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Region')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Contoh: Region Jawa Barat'),
                        
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Region')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Contoh: JABAR'),

                         Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->inline(false), // Label di atas toggle
                            ])
                     ->columns(2),

                Forms\Components\Section::make('Alamat Region')
                    ->schema([
                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->rows(3)
                            ->placeholder('Jl. Contoh No. 123')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->label('Kota')
                            ->maxLength(100)
                            ->placeholder('Contoh: Bandung'),

                        Forms\Components\TextInput::make('province')
                            ->label('Provinsi')
                            ->maxLength(100)
                            ->placeholder('Contoh: Jawa Barat'),
                    ])
                    ->columns(2),

                    Forms\Components\Section::make('Pengaturan Pembagian Pendapatan')
                        ->schema([

                           //
                        ])
                        ->columns(2), // persen pakai 2 kolom, nominal & hasil full

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Region')
                    ->sortable()
                    ->searchable(),

                  Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                     Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
             ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [
            RegionTeamRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegions::route('/'),
            // 'create' => Pages\CreateRegion::route('/create'),
            'view' => Pages\ViewRegion::route('/{record}'),
            'edit' => Pages\EditRegion::route('/{record}/edit'),
        ];
    }

     public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'city', 'province'];
    }
}
