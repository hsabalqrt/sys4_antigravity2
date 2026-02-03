<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource\RelationManagers;
use Spatie\Permission\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'المستخدمون';
    protected static ?string $modelLabel = 'دور';
    protected static ?string $pluralModelLabel = 'الأدوار';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('معلومات الدور')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم الدور')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Hidden::make('guard_name')
                            ->default('web'),
                    ]),
                Section::make('الصلاحيات')
                    ->description('تحديد الصلاحيات لهذا الدور')
                    ->schema(static::getPermissionSchema())
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('عدد الصلاحيات')
                    ->counts('permissions')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->url(fn ($record) => RoleResource::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make()->url(fn ($record) => RoleResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // Tables\Actions\ForceDeleteBulkAction::make(),
                    // Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    // public static function getEloquentQuery(): Builder
    // {
    //     return parent::getEloquentQuery()
    //         ->withoutGlobalScopes([
    //             SoftDeletingScope::class,
    //         ]);
    // }


    public static function getPermissionSchema(): array
    {
        $permissions = Permission::all();

        $groupedPermissions = $permissions->groupBy(function ($permission) {
            $name = $permission->name;
            
            if (Str::contains($name, 'client_need')) return 'Client Needs';
            if (Str::contains($name, 'tag_group')) return 'Tag Groups';
            if (Str::contains($name, 'social_media')) return 'Social Media';
            if (Str::contains($name, 'designer_dashboard')) return 'Designer Dashboard';
            if (Str::contains($name, 'reviewer_dashboard')) return 'Reviewer Dashboard';
            if (Str::contains($name, 'dashboard')) return 'Dashboards';
            if (Str::contains($name, 'distribution')) return 'Distribution';
            
            $parts = explode('_', $name);
            return ucfirst(end($parts));
        });

        $permissionSections = [];
        foreach ($groupedPermissions as $group => $perms) {
            $permissionSections[] = Section::make($group)
                ->schema([
                    CheckboxList::make('permissions_' . Str::slug($group))
                        ->label('')
                        ->options($perms->pluck('name', 'id'))
                        // Load state from the record (Role)
                        ->loadStateFromRelationshipsUsing(function (CheckboxList $component, ?Role $record) use ($perms) {
                            if (!$record) return;
                            $rolePermissionIds = $record->permissions()->pluck('id')->toArray();
                            $component->state(array_values(array_intersect($rolePermissionIds, $perms->pluck('id')->toArray())));
                        })
                        // Prevent auto-save, we handle it manually
                        ->dehydrated(false)
                        ->bulkToggleable()
                        ->columns(2)
                        ->gridDirection('row')
                ])
                ->collapsible()
                ->compact();
        }

        return [
            \Filament\Forms\Components\Grid::make(3)
                ->schema($permissionSections)
        ];
    }
}
