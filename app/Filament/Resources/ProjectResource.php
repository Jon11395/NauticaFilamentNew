<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Project;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Resources\Pages\Page;
use Guava\FilamentNestedResources\Ancestor;
use Filament\Tables\Columns\Summarizers\Sum;
use App\Filament\Resources\ProjectResource\Pages;
use Guava\FilamentNestedResources\Concerns\NestedResource;
use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;

use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Support\Enums\Alignment;



class ProjectResource extends Resource
{
    use NestedResource;

    protected static ?string $model = Project::class;

    protected static ?string $navigationGroup = 'Proyectos';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Proyectos';
    protected static ?string $breadcrumb = "Proyectos";
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('offer_amount')
                    ->label('Oferta')
                    ->prefix('₡')
                    ->required()
                    ->numeric()
                    ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Inicio')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->required()
                    ->options([
                        'in_progress' => 'En proceso',
                        'stopped' => 'Detenido',
                        'finished' => 'Terminado',
                    ])
                    ->default('in_progress'),
                Forms\Components\FileUpload::make('image')
                    ->disk('public') // use the public disk, which points to storage/app/public
                    ->directory('images/projects')
                    ->image()
                    ->label('Imagenes')
                    ->maxSize(1024),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Proyectos')
            ->description('Lista de proyectos')
            ->defaultPaginationPageOption(25)
            ->extremePaginationLinks()
            ->columns([
                Split::make([
                    Tables\Columns\ImageColumn::make('image')
                            ->label('Imagen')
                            ->defaultImageUrl(url('/images/logo.png'))
                            ->visibility('private')
                            ->circular()
                            ->size(100),
                    Stack::make([
                            Tables\Columns\TextColumn::make('name')
                            ->label('Nombre')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->size(1),
                        Tables\Columns\TextColumn::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'in_progress' => 'warning',
                                'stopped' => 'danger',
                                'finished' => 'success',
                                })
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'in_progress' => 'En progreso',
                                    'stopped' => 'Detenido',
                                    'finished' => 'Terminado',
                            })
                            ->searchable(),
                        Tables\Columns\TextColumn::make('offer_amount')
                            ->label('Oferta')
                            ->numeric()
                            ->sortable()
                            ->searchable()
                            ->money('CRC')
                            ->summarize(Sum::make()->label('Total')->money('CRC')),
                        Tables\Columns\TextColumn::make('start_date')
                            ->label('Inicio')
                            ->date()
                            ->sortable()
                            ->searchable()
                            ->icon('heroicon-m-calendar-days'),
                         
                    ])->space(2),
                ])
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])->actions([
                Tables\Actions\ViewAction::make(),
                //Tables\Actions\EditAction::make(),
                //Tables\Actions\DeleteAction::make(),
                ActivityLogTimelineTableAction::make('Activities')
                    ->label('Actividad')
                    ->color('info')
                    ->limit(15),
        
            ])
            ->filters([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                    //FilamentExportBulkAction::make('Exportar'),
                ]),
            ])->defaultSort('start_date', 'desc');;
        ;
        /*return $table
            ->heading('Proyectos')
            ->description('Lista de proyectos')
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Imagen')
                    ->circular()
                    ->defaultImageUrl(url('/images/logo.png'))
                    ->visibility('private'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('offer_amount')
                     ->label('Oferta')
                    ->numeric()
                    ->sortable()
                    ->money('CRC')
                    ->summarize(Sum::make()->label('Total')->money('CRC')),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                ->label('Estado')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'in_progress' => 'warning',
                    'stopped' => 'danger',
                    'finished' => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in_progress' => 'En progreso',
                        'stopped' => 'Detenido',
                        'finished' => 'Terminado',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                //Tables\Actions\EditAction::make(),
                //Tables\Actions\DeleteAction::make(),
                ActivityLogTimelineTableAction::make('Activities')
                    ->label('Actividad')
                    ->color('info')
                    ->limit(15),
                
                
        
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                    FilamentExportBulkAction::make('Exportar'),
                ]),
            ]);*/
    }


    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            //'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            'view' => Pages\ViewProject::route('/{record}'),

            
            // In case of relation page.
            // Make sure the name corresponds to the name of your actual relationship on the model.
            'incomes' => Pages\ManageProjectIncomes::route('/{record}/incomes'),
            'expenses' => Pages\ManageProjectExpenses::route('/{record}/expenses'),
            'contracts' => Pages\ManageProjectContracts::route('/{record}/contracts'),
            'spreadsheets' => Pages\ManageProjectSpreadsheets::route('/{record}/spreadsheets'),
 
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            //Pages\ListProjects::class,
            Pages\ViewProject::class,
            Pages\EditProject::class,
            Pages\ManageProjectIncomes::class,
            Pages\ManageProjectExpenses::class,
            Pages\ManageProjectContracts::class,
            Pages\ManageProjectSpreadsheets::class,
        ]);
    }

    public static function getAncestor(): ?Ancestor
    {
        return null;
    }
}
