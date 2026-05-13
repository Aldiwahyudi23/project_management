<?php

namespace App\Filament\Resources\Inspection\Team;

use App\Filament\Resources\Inspection\Team\RegionTeamResource\Pages;
use App\Filament\Resources\Inspection\Team\RegionTeamResource\RelationManagers;
use App\Filament\Resources\Inspection\Team\RegionTeamResource\RelationManagers\InspectionTemplatesRelationManager;
use App\Models\MasterData\RegionTeam;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegionTeamResource extends Resource
{
    protected static ?string $model = RegionTeam::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

        protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Region Team Information')
                    ->schema([

                        Forms\Components\Select::make('region_id')
                            ->label('Region')
                            ->relationship('region', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'paused' => 'Paused',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('settings')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->columnSpanFull(),

                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InspectionTemplatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegionTeams::route('/'),
            'create' => Pages\CreateRegionTeam::route('/create'),
            'edit' => Pages\EditRegionTeam::route('/{record}/edit'),
        ];
    }
}
