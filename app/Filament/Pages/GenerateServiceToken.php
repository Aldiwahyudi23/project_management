<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GenerateServiceToken extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    //Hidden Navigation\
    protected static bool $shouldRegisterNavigation = false; //Hidden from navigation

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Generate API Token';
    protected static ?string $navigationGroup = 'Service Management API';
    protected static ?string $title = 'Generate Service Token';
    protected static string $view = 'filament.pages.generate-service-token';

    // FORM STATE
    public ?int $service_user_id = null;
    public string $tokenName = '';
    public array $abilities = [];
    public ?string $plainToken = null;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('service_user_id')
                ->label('Service Account')
                ->options(
                    User::query()
                        ->where('type', 'service')
                        ->pluck('name', 'id')
                )
                ->searchable()
                ->required(),

            Forms\Components\TextInput::make('tokenName')
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
        ];
    }

    public function generate()
    {
        $serviceUser = User::where('id', $this->service_user_id)
            ->where('type', 'service')
            ->first();

        if (! $serviceUser) {
            Notification::make()
                ->title('Invalid service account')
                ->danger()
                ->send();
            return;
        }

        if (empty($this->abilities)) {
            Notification::make()
                ->title('Permission required')
                ->body('Please select at least one permission.')
                ->danger()
                ->send();
            return;
        }

        $token = $serviceUser->createToken(
            $this->tokenName,
            $this->abilities
        );

        // TOKEN ASLI HANYA SEKALI
        $this->plainToken = $token->plainTextToken;

        Notification::make()
            ->title('Token generated successfully')
            ->success()
            ->send();
    }
}
