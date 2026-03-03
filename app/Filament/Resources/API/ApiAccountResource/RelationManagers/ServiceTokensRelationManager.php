<?php

namespace App\Filament\Resources\API\ApiAccountResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ServiceTokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';
    protected static ?string $title = 'API Tokens';
    protected static ?string $recordTitleAttribute = 'name';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Token Name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('abilities')
                    ->badge()
                    ->separator(', '),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generateToken')
                    ->label('Generate Token')
                    ->icon('heroicon-o-key')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('token_name')
                            ->label('Token Name')
                            ->placeholder('inspection-backend-prod')
                            ->required(),

                        Forms\Components\CheckboxList::make('abilities')
                            ->label('API Permissions')
                            ->options([
                                'vehicles:read'   => 'Read vehicles',
                                'vehicles:create' => 'Create vehicles',
                                'vehicles:update' => 'Update vehicles',
                                'vehicles:delete' => 'Delete vehicles',
                            ])
                            ->columns(2)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $user = $this->getOwnerRecord();

                        $token = $user->createToken(
                            $data['token_name'],
                            $data['abilities']
                        );

                        Notification::make()
                            ->title('Token generated')
                            ->success()
                            ->body(
                                new HtmlString(
                                    '<strong>Copy this token now:</strong><br>' .
                                    '<code style="word-break: break-all;">' .
                                    $token->plainTextToken .
                                    '</code>'
                                )
                            )
                            ->persistent()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Revoke')
                    ->icon('heroicon-o-trash')
                    ->color('danger'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
