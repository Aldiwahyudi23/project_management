<?php

namespace App\Filament\Resources\API;

use App\Filament\Resources\API\ApiAccountResource\Pages;
use App\Filament\Resources\API\ApiAccountResource\RelationManagers\ServiceTokensRelationManager;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiAccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Service Management API';
    protected static ?string $navigationLabel = 'Service API';
    protected static ?string $title = 'Service API Accounts';
    protected static ?int $navigationSort = 2;

    /**
     * 🔒 Hanya tampilkan akun service
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'service');
    }

    /**
     * 📄 FORM
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            // type hidden
            Hidden::make('type')
                ->default('service')
                ->dehydrated(true),

            // password random hidden
            Hidden::make('password')
                ->default(fn () => Hash::make(Str::random(12)))
                ->dehydrated(true),

            Forms\Components\TextInput::make('name')
                ->label('Service Name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $state, callable $set) {
                    // hapus suffix lama kalau ada
                    $clean = Str::of($state)
                        ->replaceLast('-service', '')
                        ->trim();

                    // set ulang dengan suffix
                    $set('name', $clean . '-service');
                })
                ->helperText('Akan otomatis ditambahkan "-service"'),

            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable(),
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

    /**
     * 🔗 RELATIONS
     */
    public static function getRelations(): array
    {
        return [
            ServiceTokensRelationManager::class,
        ];
    }

    /**
     * 📌 PAGES
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiAccounts::route('/'),
            'edit'  => Pages\EditApiAccount::route('/{record}/edit'),
            // 'create' => Pages\CreateApiAccount::route('/create'),
        ];
    }
}
