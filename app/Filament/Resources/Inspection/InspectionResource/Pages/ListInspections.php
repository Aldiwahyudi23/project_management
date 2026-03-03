<?php

namespace App\Filament\Resources\Inspection\InspectionResource\Pages;

use App\Filament\Resources\Inspection\InspectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInspections extends ListRecords
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Inspeksi Baru'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Inspeksi'),
            
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => InspectionResource::getModel()::where('status', 'draft')->count()),
            
            'active' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    'accepted', 'on_the_way', 'arrived', 'in_progress'
                ]))
                ->badge(fn () => InspectionResource::getModel()::whereIn('status', [
                    'accepted', 'on_the_way', 'arrived', 'in_progress'
                ])->count()),
            
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending', 'under_review', 'revision']))
                ->badge(fn () => InspectionResource::getModel()::whereIn('status', ['pending', 'under_review', 'revision'])->count()),
            
            'completed' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['approved', 'completed']))
                ->badge(fn () => InspectionResource::getModel()::whereIn('status', ['approved', 'completed'])->count()),
            
            'cancelled' => Tab::make('Dibatalkan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
                ->badge(fn () => InspectionResource::getModel()::where('status', 'cancelled')->count()),
            
            'rejected' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge(fn () => InspectionResource::getModel()::where('status', 'rejected')->count()),
            
            'trashed' => Tab::make('Sampah')
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed())
                ->badge(fn () => InspectionResource::getModel()::onlyTrashed()->count()),
            
            'today' => Tab::make('Hari Ini')
                ->modifyQueryUsing(fn (Builder $query) => $query->today())
                ->badge(fn () => InspectionResource::getModel()::today()->count()),
            
            'upcoming' => Tab::make('Akan Datang')
                ->modifyQueryUsing(fn (Builder $query) => $query->upcoming())
                ->badge(fn () => InspectionResource::getModel()::upcoming()->count()),
            
            'past' => Tab::make('Sudah Lewat')
                ->modifyQueryUsing(fn (Builder $query) => $query->past())
                ->badge(fn () => InspectionResource::getModel()::past()->count()),
        ];
    }
}