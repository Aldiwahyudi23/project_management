<?php

namespace App\Filament\Resources\Inspection\Team\RegionResource\RelationManagers;

use App\Models\MasterData\Region;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegionTeamRelationManager extends RelationManager
{
    protected static string $relationship = 'teams';

    protected static ?string $title = 'Team Members';

    protected static ?string $modelLabel = 'Team Member';

    protected static ?string $pluralModelLabel = 'Team Members';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->required()
                    ->options(function () {
                        // Ambil user yang aktif dan belum ada di region manapun
                        $users = User::where('is_active', true)
                            ->whereDoesntHave('regionTeams')
                            ->with('roles')
                            ->get();
                        
                        // Format: Nama (role)
                        return $users->mapWithKeys(function ($user) {
                            $role = $user->getRoleNames()->first() ?? 'No Role';
                            return [$user->id => "{$user->name} ({$role})"];
                        });
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih User')
                    ->helperText('Hanya menampilkan user yang belum tergabung di region manapun'),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'paused' => 'Dibekukan',
                    ])
                    ->default('active'),

            Forms\Components\Section::make('Tagihan Inspector')
                ->schema([

                    // Harga jika diajukan oleh user sendiri
                    Forms\Components\TextInput::make('settings.inspection_price_self')
                        ->label('Harga (Self Submission)')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->reactive()
                        ->dehydrateStateUsing(fn ($state) => str_replace('.', '', $state))
                        ->afterStateHydrated(function ($set, $state) {
                            if ($state !== null) {
                                $set('settings.inspection_price_self', number_format($state, 0, ',', '.'));
                            }
                        }),

                    // Harga jika diajukan oleh pihak lain
                    Forms\Components\TextInput::make('settings.inspection_price_external')
                        ->label('Harga (External Submission)')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->reactive()
                        ->dehydrateStateUsing(fn ($state) => str_replace('.', '', $state))
                        ->afterStateHydrated(function ($set, $state) {
                            if ($state !== null) {
                                $set('settings.inspection_price_external', number_format($state, 0, ',', '.'));
                            }
                        }),
                ])
                ->columns(2), // biar rapi, 2 kolom

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('user.roles.name')
                    ->label('Role')
                    ->colors([
                        'primary' => 'coordinator',
                        'warning' => 'region_admin',
                        'success' => 'inspector',
                        'gray' => 'No Role',
                    ])
                    ->formatStateUsing(fn ($state) => 
                        match ($state) {
                            'coordinator' => 'Coordinator',
                            'region_admin' => 'Admin Wilayah',
                            'inspector' => 'Inspector',
                            default => ucfirst($state) ?? 'No Role',
                        }
                    ),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                        'warning' => 'paused',
                    ])
                    ->formatStateUsing(fn (string $state): string => 
                        match ($state) {
                            'active' => 'Aktif',
                            'inactive' => 'Tidak Aktif',
                            'paused' => 'Dibekukan',
                            default => $state,
                        }
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ditambahkan')
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
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role User')
                    ->options([
                        'coordinator' => 'Coordinator',
                        'region_admin' => 'Admin Wilayah',
                        'inspector' => 'Inspector',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('user.roles', function ($q) use ($data) {
                                $q->where('name', $data['value']);
                            });
                        }
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'paused' => 'Dibekukan',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Team Member'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                // Tombol Alihkan Region
                Tables\Actions\Action::make('transfer')
                    ->label('Alihkan Region')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->modalHeading('Alihkan Team Member ke Region Lain')
                    ->modalDescription(fn ($record) => "Anda akan mengalihkan {$record->user->name} dari region {$this->getOwnerRecord()->name} ke region lain.")
                    ->form([
                        Forms\Components\Select::make('target_region_id')
                            ->label('Region Tujuan')
                            ->required()
                            ->options(function () {
                                // Ambil semua region kecuali region yang sedang diakses
                                $currentRegionId = $this->getOwnerRecord()->id;
                                
                                return Region::where('id', '!=', $currentRegionId)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih Region Tujuan'),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->placeholder('Opsional: Tambahkan catatan jika diperlukan'),
                    ])
                    ->action(function (array $data, $record): void {
                        try {
                            // Simpan data ke region baru
                            $record->region_id = $data['target_region_id'];
                            $record->save();
                            
                            Notification::make()
                                ->title('Berhasil!')
                                ->body("Team member berhasil dialihkan ke region baru.")
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal!')
                                ->body("Terjadi kesalahan: " . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('Ya, Alihkan Sekarang')
                    ->modalCancelActionLabel('Batal'),
                
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
            ->defaultSort('user.name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['user.roles']);
    }
}