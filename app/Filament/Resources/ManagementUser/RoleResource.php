<?php

namespace App\Filament\Resources\ManagementUser;

use App\Filament\Resources\ManagementUser\RoleResource\Pages;
use App\Filament\Resources\ManagementUser\RoleResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 2;

    /**
     * 🔒 FILTER QUERY
     * Sembunyikan role 'super_admin' jika yang login bukan super_admin.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        if (!Auth::user()->hasRole('super_admin')) {
            $query->where('name', '!=', 'super_admin');
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Peran')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->label('Nama Peran')
                            ->placeholder('Masukkan nama peran')
                            /**
                             * 🔒 HANYA NAMA YANG DIKUNCI
                             * Jika ini record super_admin, input nama menjadi disabled/readonly.
                             * Tapi permissions di bawah tetap bisa diedit.
                             */
                            ->disabled(fn (?Role $record) => $record?->name === 'super_admin')
                            ->dehydrated(), // Tetap kirim data ke backend saat save
                    ])
                    ->columns(2),
                
                Section::make('Manajemen Izin')
                    ->description('Pilih izin untuk peran ini.')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->options(function () {
                                $permissions = Permission::all()->sortBy('name');
                                $groups = config('permissions.groups', []); 
                                $result = [];

                                foreach ($groups as $groupName => $keywords) {
                                    $filtered = $permissions->filter(function ($perm) use ($keywords) {
                                        foreach ($keywords as $keyword) {
                                            if (str_contains($perm->name, $keyword)) return true;
                                        }
                                        return false;
                                    });

                                    if ($filtered->isNotEmpty()) {
                                        $result[$groupName] = $filtered->pluck('name', 'id')->toArray();
                                    }
                                }
                                return $result;
                            })
                            ->bulkToggleable()
                            ->searchable()
                            ->columns(3)
                            ->relationship('permissions', 'name'),
                            // 🔓 Bagian disabled dihapus agar permissions tetap bisa diedit
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->color('primary'),
                
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                // 🔒 Edit Action Logic
                Tables\Actions\EditAction::make()
                    ->visible(fn (Role $record) => 
                        $record->name !== 'super_admin' || Auth::user()->hasRole('super_admin')
                    ),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(fn (Tables\Actions\DeleteBulkAction $action) => 
                            $action->getRecords()->where('name', '!=', 'super_admin')->each->delete()
                        ),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }


    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'description'];
    }
}

// Di dalam RolePolicy.php, tambahkan logika:
// public function delete(User $user, Role $role): bool
// {
//     /**
//      * Syarat penghapusan:
//      * 1. User harus punya permission 'delete_management::user::role'
//      * 2. Nama role yang akan dihapus TIDAK BOLEH 'super_admin'
//      */
//     return $user->can('delete_management::user::role') && $role->name !== 'super_admin';
// }