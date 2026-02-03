<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UsersResource\Pages;
use App\Filament\Resources\UsersResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
// use Filament\Infolists\Infolist;
// use Filament\Infolists\Components\TextEntry;

use App\Filament\Exports\UserExporter;
use Filament\Tables\Actions\ExportAction;
use App\Filament\Imports\UserImporter;
use Filament\Tables\Actions\ImportAction;

use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;


/**
 * مورد Filament لإدارة المستخدمين (Users).
 *
 * يوفر هذا المورد واجهة متكاملة لإنشاء وعرض وتعديل وحذف بيانات المستخدمين،
 * مع عرض تفصيلي لمعلوماتهم الشخصية والوظيفية.
 */
class UsersResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = User::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'المستخدمون';
    protected static ?string $pluralLabel = 'المستخدمون';
    protected static ?string $label = 'مستخدم';
    protected static ?string $slug = 'users';
    protected static ?string $navigationGroup = 'المستخدمون';
    protected static ?int $navigationSort = 0;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'مستخدم';
    protected static ?string $pluralModelLabel = 'المستخدمون';
    protected static ?string $modelLabelPlural = 'المستخدمون';
    protected static ?string $modelLabelSingular = 'مستخدم';
    protected static ?string $modelLabelSingularPlural = 'المستخدم';
    protected static ?string $navigationBadge = 'جديد';
    protected static ?string $navigationBadgeColor = 'success';
    protected static ?string $navigationSearch = 'true';
    protected static ?string $navigationSearchPlaceholder = 'ابحث عن مستخدم...';

    /**
     * يقوم بإرجاع شارة (badge) التنقل للمورد.
     *
     * @return string|null
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    /**
     * يقوم بإرجاع لون شارة (badge) التنقل للمورد.
     *
     * @return string|null
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::count() < 10 ? 'warning' : 'primary';
    }

    protected static ?string $navigationBadgeTooltip = 'عدد المستخدمون';

    /**
     * يقوم بتعريف مكونات قائمة المعلومات (Infolist) لعرض تفاصيل المستخدم.
     *
     * @param  \Filament\Infolists\Infolist  $infolist قائمة معلومات Filament.
     * @return \Filament\Infolists\Infolist قائمة المعلومات المعرفة.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make()
                    ->schema([
                        \Filament\Infolists\Components\Split::make([
                            \Filament\Infolists\Components\ImageEntry::make('profile_image')
                                ->hiddenLabel()
                                ->circular()
                                ->grow(false),
                            \Filament\Infolists\Components\Grid::make(2)
                                ->schema([
                                    \Filament\Infolists\Components\Group::make([
                                        TextEntry::make('name')
                                            ->label('الاسم')
                                            ->weight(\Filament\Support\Enums\FontWeight::Bold),
                                        TextEntry::make('email')
                                            ->label('البريد الإلكتروني')
                                            ->icon('heroicon-m-envelope')
                                            ->copyable(),
                                    ]),
                                    \Filament\Infolists\Components\Group::make([
                                        TextEntry::make('username')
                                            ->label('اسم المستخدم')
                                            ->icon('heroicon-m-at-symbol'),
                                        TextEntry::make('status')
                                            ->label('الحالة')
                                            ->badge()
                                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'غير نشط'),
                                    ]),
                                ]),
                        ])->from('md'),
                    ]),

                \Filament\Infolists\Components\Section::make('معلومات الاتصال')
                    ->schema([
                        TextEntry::make('work_phone_number')
                            ->label('رقم هاتف العمل')
                            ->icon('heroicon-m-phone'),
                        TextEntry::make('personal_phone_number')
                            ->label('رقم الهاتف الشخصي')
                            ->icon('heroicon-m-device-phone-mobile'),
                    ])->columns(2),

                \Filament\Infolists\Components\Section::make('معلومات التوظيف')
                    ->schema([
                        TextEntry::make('hire_date')
                            ->label('تاريخ التوظيف')
                            ->date()
                            ->icon('heroicon-m-calendar'),
                    ])->columns(2),
            ]);
    }

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل المستخدمين.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('معلومات المستخدم')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('الاسم الكامل')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('username')
                            ->label('اسم المستخدم')
                            ->required()
                            ->unique(table: 'users', column: 'username', ignorable: fn($record) => $record)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->required()
                            ->email()
                            ->unique(table: 'users', column: 'email', ignorable: fn($record) => $record)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->required(fn(string $context) => $context === 'create') // Only required on create
                            ->dehydrated(fn($state) => filled($state)) // don't save confirmation field
                            ->dehydrateStateUsing(fn($state) => bcrypt($state))
                            ->maxLength(255),
                    ])->columns(2),
                Section::make('معلومات الاتصال')
                    ->schema([
                        Forms\Components\TextInput::make('work_phone_number')
                            ->label('رقم الهاتف للعمل')
                            ->tel()
                            ->maxLength(9),
                        Forms\Components\TextInput::make('personal_phone_number')
                            ->label('رقم الهاتف الشخصي')
                            ->tel()
                            ->maxLength(9),
                    ])->columns(2),
                Section::make('الصورة الشخصية')
                    ->description('يمكنك تحميل صورة شخصية للمستخدم')
                    ->schema([
                        Forms\Components\FileUpload::make('profile_image')
                            ->label('')
                            ->image()
                            ->disk('public')
                            ->directory('profile_images')
                            ->preserveFilenames(),
                    ]),
                Section::make('تاريخ التوظيف والحالة')
                    ->schema([
                        Forms\Components\DatePicker::make('hire_date')
                            ->label('تاريخ التوظيف')
                            ->native(false)
                            ->format('Y-m-d')
                            ->required(),
                        Forms\Components\Toggle::make('status')
                            ->label('الحالة')
                            ->onColor('success')
                            ->offColor('danger')
                            // Disable the toggle for the current authenticated user
                            ->disabled(fn($record) => $record?->id === auth()->id())
                            ->default(true)
                    ])->columns(1),


            Section::make('إدارة الصلاحيات')
                ->schema([
                    Actions::make([
                        Action::make('managePermissions')
                            ->label('إدارة الصلاحيات والأدوار')
                            ->icon('heroicon-o-key')
                            ->color('primary')
                            ->modalWidth('7xl')
                            ->modalHeading('إدارة صلاحيات المستخدم')
                            ->modalSubmitActionLabel('حفظ التغييرات')
                            ->stickyModalHeader()
                            ->form(fn (User $record) => static::getPermissionSchema($record))
                            ->action(function (array $data, User $record) {
                                // 1. Sync Roles
                                if (isset($data['roles'])) {
                                    // Spatie syncRoles expects names or objects. Arrays of IDs are treated as names which causes errors.
                                    // We must resolve IDs to Role objects.
                                    $roleIds = $data['roles'];
                                    $roles = Role::whereIn('id', $roleIds)->get();
                                    $record->syncRoles($roles);
                                } else {
                                    $record->syncRoles([]);
                                }

                                // 2. Sync Permissions
                                $allPermissionIds = [];
                                foreach ($data as $key => $value) {
                                    if (Str::startsWith($key, 'permissions_')) {
                                        if (is_array($value)) {
                                            $allPermissionIds = array_merge($allPermissionIds, $value);
                                        }
                                    }
                                }

                                // Prepare IDs
                                $allPermissionIds = array_map(function ($id) {
                                    return (int) $id;
                                }, $allPermissionIds);

                                // Pass IDs directly, Spatie usually handles integer IDs correctly if they exist.
                                // If issues persist, we can fetch models: Permission::whereIn('id', $allPermissionIds)->get()
                                $validPermissions = Permission::whereIn('id', $allPermissionIds)->get();
                                $record->syncPermissions($validPermissions);

                                Notification::make()
                                    ->title('تم تحديث الصلاحيات بنجاح')
                                    ->success()
                                    ->send();
                            }),
                    ])->fullWidth(),
                ])->columnSpanFull(),
            ])->columns(2);
    }


    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض المستخدمين.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('profile_image')
                    ->circular()
                    ->label(''),
                Tables\Columns\TextColumn::make('name')->label('الاسم')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('username')->sortable()->searchable()->toggleable()->label('اسم المستخدم'),
                Tables\Columns\TextColumn::make('email')->sortable()->searchable()->toggleable()->label('البريد الإلكتروني'),
                Tables\Columns\TextColumn::make('work_phone_number')->sortable()->searchable()->toggleable()->label('رقم الهاتف العمل'),
                Tables\Columns\TextColumn::make('personal_phone_number')->sortable()->searchable()->toggleable()->label('رقم الهاتف الشخصي'),
                // Tables\Columns\TextColumn::make('profile_image')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('hire_date')->sortable()->searchable()->toggleable()->label('تاريخ التوظيف'),
                Tables\Columns\BooleanColumn::make('status')->sortable()->searchable()->toggleable()->label('الحالة'),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->query(fn(Builder $query) => $query->where('status', true))
                    ->label('المستخدمون النشطون'),
                Tables\Filters\Filter::make('inactive')
                    ->query(fn(Builder $query) => $query->where('status', false))
                    ->label('المستخدمون غير النشطين'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                ->slideOver()->modalWidth('lg'),
                Tables\Actions\ViewAction::make()->label('عرض')->icon('heroicon-o-eye')->color('primary'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(UserExporter::class)
                    ->label('تصدير')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->fileDisk('exports'),
                ImportAction::make()
                    ->importer(UserImporter::class)
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
            //
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUsers::route('/create'),
            'edit' => Pages\EditUsers::route('/{record}/edit'),
            'view' => Pages\ViewUsers::route('/{record}'),
        ];
    }


    public static function getPermissionSchema(User $user): array
    {
        // 1. Roles Section
        $schema = [
            Section::make('الأدوار')
                ->schema([
                    CheckboxList::make('roles')
                        ->label('الأدوار المسندة')
                        ->options(Role::all()->pluck('name', 'id')) // Spatie Role model
                        ->default(fn() => $user->roles->pluck('id')->toArray())
                        ->bulkToggleable()
                        ->columns(4)
                        ->gridDirection('row'),
                ])
                ->compact()
        ];

        // 2. Permissions Grouping Logic
        $permissions = Permission::all(); // Spatie Permission model
        // Fetch fresh user permissions to ensure modal reflects actual DB state
        $userPermissionIds = $user->permissions()->pluck('id')->toArray();

        $groupedPermissions = $permissions->groupBy(function ($permission) {
            $name = $permission->name;
            
            // Custom grouping based on known prefixes/suffixes
            if (Str::contains($name, 'client_need')) return 'Client Needs';
            if (Str::contains($name, 'tag_group')) return 'Tag Groups';
            if (Str::contains($name, 'social_media')) return 'Social Media';
            if (Str::contains($name, 'designer_dashboard')) return 'Designer Dashboard';
            if (Str::contains($name, 'reviewer_dashboard')) return 'Reviewer Dashboard';
            if (Str::contains($name, 'dashboard')) return 'Dashboards';
            if (Str::contains($name, 'distribution')) return 'Distribution';
            
            // Fallback: Group by the last part of the name (usually the resource name)
            $parts = explode('_', $name);
            return ucfirst(end($parts));
        });

        // 3. Build Permission Sections
        $permissionSections = [];
        foreach ($groupedPermissions as $group => $perms) {
            $permissionSections[] = Section::make($group)
                ->schema([
                    CheckboxList::make('permissions_' . Str::slug($group))
                        ->label('')
                        ->options($perms->pluck('name', 'id'))
                        ->default(fn() => array_values(array_intersect($userPermissionIds, $perms->pluck('id')->toArray())))
                        ->bulkToggleable()
                        ->columns(2)
                        ->gridDirection('row')
                ])
                ->collapsible()
                ->compact();
        }

        $schema[] = Section::make('الصلاحيات المباشرة')
             ->description('تحديد صلاحيات خاصة للمستخدم بعيداً عن الأدوار')
             ->schema([
                 \Filament\Forms\Components\Grid::make(3)
                    ->schema($permissionSections)
             ]);

        return $schema;
    }
}
