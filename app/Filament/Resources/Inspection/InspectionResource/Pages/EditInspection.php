<?php

namespace App\Filament\Resources\Inspection\InspectionResource\Pages;

use App\Filament\Resources\Inspection\InspectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInspection extends EditRecord
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('Detail'),
            
            Actions\Action::make('complete')
                ->label('Selesaikan Inspeksi')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'completed']);
                    $this->notify('success', 'Inspeksi telah diselesaikan');
                })
                ->visible(fn () => $this->record->status !== 'completed'),
            
            Actions\Action::make('cancel')
                ->label('Batalkan Inspeksi')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'cancelled']);
                    $this->notify('success', 'Inspeksi telah dibatalkan');
                })
                ->visible(fn () => !in_array($this->record->status, ['completed', 'cancelled'])),
            
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Inspeksi berhasil diperbarui';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure submitted_by tidak berubah
        if (isset($data['submitted_by'])) {
            unset($data['submitted_by']);
        }
        
        return $data;
    }
}