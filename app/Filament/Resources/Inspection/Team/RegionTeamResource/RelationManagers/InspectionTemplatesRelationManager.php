<?php

namespace App\Filament\Resources\Inspection\Team\RegionTeamResource\RelationManagers;

use App\Models\DirectDB\Inspection\Template;
use App\Models\DirectDB\Inspection\TemplateRepot;
use App\Models\MasterData\UserInspectionTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InspectionTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'inspectionTemplates';

    protected static ?string $title = 'Inspection Templates';

    public function form(Form $form): Form
    {
        return $form
                        ->schema([

                Forms\Components\Select::make('template_type')
                    ->options([
                        'form' => 'Form Template',
                        'report' => 'Report Template',
                    ])
                    ->live()
                    ->required(),

                Forms\Components\Select::make('template_id')
                    ->label('Template')
                    ->options(function (callable $get) {

                        $type = $get('template_type');

                        if (!$type) {
                            return [];
                        }

                        $ownerRecord = $this->getOwnerRecord();

                        $userId = $ownerRecord->user_id;

                        // template yang sudah dipakai user
                        $usedTemplateIds = UserInspectionTemplate::query()
                            ->where('user_id', $userId)
                            ->where('template_type', $type)
                            ->pluck('template_id')
                            ->toArray();

                        // INCLUDE current record saat edit
                        $currentRecord = $this->getMountedTableActionRecord();

                        if ($currentRecord?->template_id) {

                            $usedTemplateIds = array_diff(
                                $usedTemplateIds,
                                [$currentRecord->template_id]
                            );
                        }

                        // =========================
                        // FORM TEMPLATE
                        // =========================
                        if ($type === 'form') {

                            return Template::query()
                                ->whereNotIn('id', $usedTemplateIds)
                                ->pluck('name', 'id')
                                ->toArray();
                        }

                        // =========================
                        // REPORT TEMPLATE
                        // =========================
                        return TemplateRepot::query()
                            ->whereNotIn('id', $usedTemplateIds)
                            ->pluck('name', 'id')
                            ->toArray();
                    })

                    // 🔥 INI YANG PENTING
                    ->getOptionLabelUsing(function ($value, callable $get) {

                        $type = $get('template_type');

                        if (!$value || !$type) {
                            return null;
                        }

                        if ($type === 'form') {

                            return Template::find($value)?->name;
                        }

                        return TemplateRepot::find($value)?->name;
                    })

                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Toggle::make('is_default')
                    ->default(false),

                Forms\Components\Toggle::make('is_active')
                    ->default(true),

                Forms\Components\KeyValue::make('config')
                    ->columnSpanFull(),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('template_type')
                    ->colors([
                        'success' => 'form',
                        'warning' => 'report',
                    ]),

                Tables\Columns\TextColumn::make('template_name')
                    ->label('Template')
                    ->getStateUsing(function ($record) {

                        if ($record->template_type === 'form') {

                            return Template::find($record->template_id)?->name;
                        }

                        return TemplateRepot::find($record->template_id)?->name;
                    })
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {

                        $ownerRecord = $this->getOwnerRecord();

                        $data['user_id'] = $ownerRecord->user_id;

                        // auto isi name dari template
                        if ($data['template_type'] === 'form') {

                            $template = Template::find($data['template_id']);

                        } else {

                            $template = TemplateRepot::find($data['template_id']);
                        }

                        if ($template) {
                            $data['name'] = $template->name;
                        }

                        return $data;
                    }),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
