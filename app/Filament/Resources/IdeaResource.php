<?php

namespace App\Filament\Resources;

use App\Filament\Exports\IdeaExporter;
use App\Filament\Imports\IdeaImporter;
use App\Filament\Resources\IdeaResource\Pages;
use App\Models\Idea;
use App\Models\Tag;
use App\Models\TagIdea;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;

use Filament\Forms\Components\{
    TextInput,
    Textarea,
    DateTimePicker,
    Toggle,
    FileUpload,
    MultiSelect
};
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Columns\{
    TextColumn,
    IconColumn
};
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * مورد Filament لإدارة الأفكار (Ideas).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف الأفكار،
 * مع إمكانية ربطها بالعملاء، الوسوم، والمواقع.
 */
class IdeaResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = Idea::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';
    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?string $navigationLabel = 'الأفكار';
    protected static ?string $pluralLabel = 'الأفكار';
    protected static ?string $label = 'أفكار';
    protected static ?string $slug = 'ideas';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'أفكار';
    protected static ?string $pluralModelLabel = 'الأفكار';
    protected static ?string $modelLabelPlural = 'الأفكار';
    protected static ?string $modelLabelSingular = 'أفكار';
    protected static ?string $modelLabelSingularPlural = 'الأفكار';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل الأفكار.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Textarea::make('content')
                ->rows(5)
                ->columnSpan('full'),

            Textarea::make('description')
                ->rows(4)
                ->columnSpan('full'),

            Toggle::make('repeat_for_clients')
                ->label('Repeat for Clients'),

            DateTimePicker::make('scheduled_at'),

            FileUpload::make('idea_file')
                ->label('Idea File')
                ->directory('ideas'),

            Toggle::make('is_visible_in_generator')
                ->label('Visible in Generator'),

            MultiSelect::make('clients')
                ->relationship('clients', 'company')
                ->label('Assigned Clients')
                ->preload(),

            MultiSelect::make('tags')
                ->relationship('tags', 'name')
                ->label('Tags')
                ->live()
                ->preload(),

            MultiSelect::make('locations')
                ->relationship(
                    'locations',
                    'name',
                    fn (Builder $query, Get $get) => $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $get('tags') ?? []))
                )
                ->label('Locations')
                ->preload(),
        ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض الأفكار.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('repeat_for_clients')->boolean(),
                IconColumn::make('is_visible_in_generator')->boolean(),
                TextColumn::make('scheduled_at')->dateTime()->sortable(),
                TextColumn::make('clients_count')->counts('clients')->label('Clients'),
                TextColumn::make('tags_count')->counts('tags')->label('Tags'),
                TextColumn::make('tags.name'),

            ])
            ->filters([
                SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->searchable()
                    ->preload()

            ])
            ->actions([
                Tables\Actions\EditAction::make()->slideOver(),
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(IdeaExporter::class),
            ]);
    }

    /**
     * يقوم بإرجاع مديري العلاقات (Relation Managers) لهذا المورد.
     *
     * @return array
     */
    public static function getRelations(): array
    {
        return [
            // لاحقاً: يمكن إضافة RelationManagers
        ];
    }

    /**
     * يقوم بإرجاع صفحات (Pages) لهذا المورد.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIdeas::route('/'),
            'create' => Pages\CreateIdea::route('/create'),
            // 'edit' => Pages\EditIdea::route('/{record}/edit'),
        ];
    }
}
