<?php

namespace App\Filament\Resources\ManagementUser;

use App\Filament\Resources\ManagementUser\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 1;

    /**
     * 🔒 FILTER QUERY (Table & Global Search)
     * 1. Hanya tampilkan user dengan type = user.
     * 2. Jika bukan Super Admin, sembunyikan user yang memiliki role 'super_admin'.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $query = parent::getEloquentQuery()->where('type', 'user');

        // Jika user yang login TIDAK punya role super_admin
        if (!$user->hasRole('super_admin')) {
            $query->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'super_admin');
            });
        }

        return $query;
    }

    /**
     * 📄 FORM
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('type')
                    ->default('user')
                    ->dehydrated(true),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('phone')
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->default('Mobil123'),

                Forms\Components\Select::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        /**
                         * 🔒 FILTER ROLE OPTION
                         * Jika bukan super_admin, pilihan role 'super_admin' dihilangkan dari dropdown.
                         */
                        fn (Builder $query) => Auth::user()->hasRole('super_admin')
                            ? $query
                            : $query->where('name', '!=', 'super_admin')
                    )
                    ->preload()
                    ->multiple()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set) {

                        if (!$state) {
                            $set('permissions', []);
                            return;
                        }

                        $permissions = Role::whereIn('id', $state)
                            ->with('permissions')
                            ->get()
                            ->pluck('permissions')
                            ->flatten()
                            ->pluck('id')
                            ->unique()
                            ->toArray();

                        $set('permissions', $permissions);
                    }),

                Forms\Components\CheckboxList::make('permissions')
                    ->label('User Permissions (Override Role)')
                    ->relationship('permissions', 'name')
                    ->columns(2)
                    ->options(function (Forms\Get $get) {

                        $roleIds = $get('roles');

                        if (!$roleIds) {
                            return [];
                        }

                        return Role::whereIn('id', $roleIds)
                            ->with('permissions')
                            ->get()
                            ->pluck('permissions')
                            ->flatten()
                            ->pluck('name', 'id')
                            ->unique()
                            ->toArray();
                    })
                    ->helperText('Permission ini mengikuti Role yang dipilih, tetapi bisa diubah untuk user tertentu.'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    /**
     * 📊 TABLE
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Roles')->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Email Terverifikasi')
                    ->icon(fn ($record) => $record->email_verified_at ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->color(fn ($record) => $record->email_verified_at ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('created_at')->label('Dibuat Pada')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

// Di dalam UserPolicy.php, tambahkan logika:

// public function update(User $user, User $model): bool
// {
//     // 1. Cek dulu apakah user login punya permission dasar untuk update
//     if (!$user->can('update_management::user::user')) {
//         return false;
//     }

//     // 2. Jika user yang akan diedit ($model) adalah Super Admin
//     if ($model->hasRole('super_admin')) {
//         // Hanya izinkan jika yang mengedit ($user) juga Super Admin
//         return $user->hasRole('super_admin');
//     }

//     // 3. Jika bukan Super Admin yang diedit, izinkan (karena poin 1 sudah terpenuhi)
//     return true;
// }