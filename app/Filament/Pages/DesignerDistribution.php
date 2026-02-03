<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Grouping\Group;
use App\Services\DesignerDistributionService;
use App\Models\ClientDesigner;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Pages\TagDistribution;
use Filament\Resources\Components\Tab;

/**
 * صفحة Filament مخصصة لتوزيع المصممين على العملاء.
 *
 * توفر هذه الصفحة واجهة لتوزيع المصممين على العملاء بشكل تلقائي أو يدوي،
 * وعرض التوزيعات الحالية، وتغييرها أو إلغائها.
 */
class DesignerDistribution extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * أيقونة التنقل للصفحة.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    /**
     * مجموعة التنقل التي تنتمي إليها الصفحة.
     *
     * @var string|null
     */
    protected static ?string $navigationGroup = 'إدارة التوزيع';

    /**
     * اسم الصفحة في قائمة التنقل.
     *
     * @var string|null
     */
    protected static ?string $navigationLabel = 'توزيع المصممين';

    /**
     * عنوان الصفحة.
     *
     * @var string|null
     */
    protected static ?string $title = 'توزيع المصممين على العملاء';

    /**
     * عرض Blade المستخدم لعرض الصفحة.
     *
     * @var string
     */
    protected static string $view = 'filament.pages.designer-distribution';

    /**
     * الأسبوع المحدد حاليًا.
     *
     * @var string|null
     */
    public ?string $selectedWeek = null;

    /**
     * إحصائيات التوزيع.
     *
     * @var array|null
     */
    public ?array $distributionStats = null;

    /**
     * التبويب النشط حالياً.
     *
     * @var string
     */
    public ?string $activeTab = 'all';

    /**
     * يتم استدعاؤها عند تحميل الصفحة.
     *
     * @return void
     */
    public static function canAccess(): bool
    {
        return auth()->user()->can('view_designer_distribution');
    }

    public function mount(): void
    {
        // تعيين الأسبوع الحالي كقيمة افتراضية
        $this->selectedWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->loadDistributionStats();
    }

    /**
     * يتم استدعاؤه عند تحديث التبويب النشط.
     *
     * @return void
     */
    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }






    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض توزيعات المصممين.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('client.company')
                    ->label('اسم الشركة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // TextColumn::make('client.client_name')
                //     ->label('اسم العميل')
                //     ->searchable()
                //     ->sortable(),

                TextColumn::make('subscription.is_main')
                    ->label('نوع الاشتراك')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn($state) => $state ? 'أساسي' : 'ثانوي'),

                TextColumn::make('client.category.name')
                    ->label('التصنيف')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('client.customer_rating_value')
                    ->label('التقييم')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('subscription.designs_count')
                    ->label('التصاميم المطلوبة')
                    ->badge()
                    ->color('success')
                    ->default('0'),



                TextColumn::make('designer.rate')
                    ->label('تقييم المصمم')
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state >= 8 => 'success',
                        $state >= 6 => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('week_start_date')
                    ->label('تاريخ الأسبوع')
                    ->date('Y-m-d')
                    ->sortable(),
            ])
            ->filters([
                // يمكن إضافة فلاتر حسب الحاجة
            ])
            ->actions([
                Action::make('changeDesigner')
                    ->label('نقل الى مصمم اخر')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\Select::make('designer_id')
                            ->label('اختر مصمم')
                            ->options(function () {
                                return Designer::with('user')
                                    ->get()
                                    ->pluck('user.name', 'id');
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (ClientDesigner $record, array $data) {
                        $record->update([
                            'designer_id' => $data['designer_id'],
                        ]);

                        Notification::make()
                            ->title('تم تغيير المصمم بنجاح')
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                    }),

                Action::make('swapAssignment')
                    ->label('تبديل مع عميل آخر')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->form([
                        \Filament\Forms\Components\Select::make('target_assignment_id')
                            ->label('اختر العميل للتبديل معه')
                            ->options(function (ClientDesigner $record) {
                                return ClientDesigner::with(['client', 'designer.user', 'subscription'])
                                    ->where('week_start_date', $record->week_start_date)
                                    ->where('id', '!=', $record->id)
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        $clientName = $item->client->company ?? $item->client->client_name;
                                        $designerName = $item->designer->user->name ?? 'غير معروف';
                                        $subType = $item->subscription ? ($item->subscription->is_main ? 'أساسي' : 'ثانوي') : '-';
                                        return [$item->id => "{$clientName} ({$subType}) - مع المصمم: {$designerName}"];
                                    });
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (ClientDesigner $record, array $data) {
                        $targetRecord = ClientDesigner::find($data['target_assignment_id']);

                        if (!$targetRecord) {
                            Notification::make()
                                ->title('حدث خطأ')
                                ->body('التعيين المستهدف لم يعد موجوداً')
                                ->danger()
                                ->send();
                            return;
                        }

                        $currentDesignerId = $record->designer_id;
                        $targetDesignerId = $targetRecord->designer_id;

                        // تنفيذ التبديل
                        $record->update(['designer_id' => $targetDesignerId]);
                        $targetRecord->update(['designer_id' => $currentDesignerId]);

                        Notification::make()
                            ->title('تم تبديل العملاء بنجاح')
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                    }),

                Action::make('removeAssignment')
                    ->label('إلغاء التعيين')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ClientDesigner $record) {
                        $record->delete();

                        Notification::make()
                            ->title('تم إلغاء التعيين بنجاح')
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                    }),

                Action::make('togglePin')
                    ->label(fn(ClientDesigner $record) => $record->client->fixed_designer_id === $record->designer_id ? 'إلغاء التثبيت' : 'تثبيت العميل')
                    ->icon(fn(ClientDesigner $record) => $record->client->fixed_designer_id === $record->designer_id ? 'heroicon-s-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn(ClientDesigner $record) => $record->client->fixed_designer_id === $record->designer_id ? 'success' : 'gray')
                    ->action(function (ClientDesigner $record) {
                        $client = $record->client;

                        if ($client->fixed_designer_id === $record->designer_id) {
                            // Unpin
                            $client->update(['fixed_designer_id' => null]);
                            Notification::make()
                                ->title('تم إلغاء تثبيت العميل')
                                ->success()
                                ->send();
                        } else {
                            // Pin
                            $client->update(['fixed_designer_id' => $record->designer_id]);
                            Notification::make()
                                ->title('تم تثبيت العميل عند هذا المصمم')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                Action::make('stats_distributed')
                    ->label(fn() => 'العملاء الموزعين: ' . ($this->distributionStats['total_assignments'] ?? 0))
                    ->color('success')
                    ->badge()
                    ->disabled(),

                Action::make('stats_pending')
                    ->label(fn() => 'العملاء قيد الانتظار: ' . ($this->distributionStats['pending_count'] ?? 0))
                    ->color('danger')
                    ->badge()
                    ->disabled(),

                Action::make('addAssignment')
                    ->label('إضافة تعيين يدوي')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->modalHeading('إضافة تعيين جديد')
                    ->modalDescription('املأ النموذج لإضافة تعيين يدوي لعميل على مصمم.')
                    ->form([
                        \Filament\Forms\Components\Select::make('designer_id')
                            ->label('المصمم')
                            ->options(function () {
                                $weekStart = Carbon::parse($this->selectedWeek)->format('Y-m-d');

                                $designers = Designer::with('user')->get();
                                $assignments = ClientDesigner::where('week_start_date', $weekStart)
                                    ->with('subscription')
                                    ->get()
                                    ->groupBy('designer_id');

                                return $designers->mapWithKeys(function ($designer) use ($assignments) {
                                    $designerAssignments = $assignments->get($designer->id) ?? collect();
                                    $currentLoad = $designerAssignments->sum(fn($a) => $a->subscription->designs_count ?? 0);
                                    $maxCapacity = $designer->max_capacity ?? 0;
                                    $available = max(0, $maxCapacity - $currentLoad);

                                    $label = "{$designer->user->name} (متاح: {$available})";
                                    return [$designer->id => $label];
                                });
                            })
                            ->searchable()
                            ->required(),
                        \Filament\Forms\Components\Select::make('subscription_id')
                            ->label('الاشتراك')
                            ->options(function () {
                                $weekStart = Carbon::parse($this->selectedWeek);
                                $weekEnd = $weekStart->copy()->addDays(6)->format('Y-m-d');

                                $assignedIds = ClientDesigner::where('week_start_date', $this->selectedWeek)
                                    ->whereNotNull('subscription_id')
                                    ->pluck('subscription_id')
                                    ->toArray();

                                return Subscription::with('client')
                                    ->whereHas('client', function ($query) {
                                        $query->where('status', 1);
                                    })
                                    ->where('status', 'active')
                                    ->whereNotIn('id', $assignedIds)
                                    ->where('start_date', '<=', $weekEnd)
                                    ->get()
                                    ->mapWithKeys(function ($subscription) {
                                        $clientName = $subscription->client ? ($subscription->client->company ?? $subscription->client->client_name) : 'بدون عميل';
                                        $type = $subscription->is_main ? 'أساسي' : 'ثانوي';
                                        $designsCount = $subscription->designs_count ?? 0;

                                        return [$subscription->id => "{$clientName} - {$type} - عدد التصاميم: {$designsCount}"];
                                    });
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $subscription = Subscription::find($data['subscription_id']);

                        ClientDesigner::updateOrCreate(
                            [
                                'subscription_id' => $data['subscription_id'],
                                'week_start_date' => $this->selectedWeek,
                            ],
                            [
                                'client_id' => $subscription->client_id,
                                'designer_id' => $data['designer_id'],
                            ]
                        );

                        Notification::make()
                            ->title('تم إضافة التعيين بنجاح')
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                    }),

                Action::make('distributeTags')
                    ->label('توزيع التاقات')
                    ->icon('heroicon-o-tag')
                    ->color('info')
                    ->url(fn() => TagDistribution::getUrl(['week' => $this->selectedWeek])),

                Action::make('autoDistribute')
                    ->label('توزيع تلقائي')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد التوزيع التلقائي')
                    ->modalDescription('سيتم توزيع جميع العملاء على المصممين بشكل تلقائي بناءً على الخوارزمية الذكية. هل تريد المتابعة؟')
                    ->action(function () {
                        $this->performAutoDistribution();
                    }),

                Action::make('clearDistribution')
                    ->label('مسح التوزيع')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد مسح التوزيع')
                    ->modalDescription('سيتم حذف جميع التعيينات لهذا الأسبوع. هل أنت متأكد؟')
                    ->action(function () {
                        $this->clearDistribution();
                    }),
            ])
            ->emptyStateHeading('لا يوجد توزيع لهذا الأسبوع')
            ->emptyStateDescription('استخدم زر "توزيع تلقائي" لتوزيع العملاء على المصممين')
            ->emptyStateIcon('heroicon-o-inbox')
            ->paginated([10, 25, 50, 100, 'all']);
    }

    /**
     * يقوم بإرجاع استعلام Eloquent لبيانات الجدول.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getTableQuery(): Builder
    {
        return ClientDesigner::query()
            ->with(['client.category', 'subscription', 'designer.user'])
            ->whereNotNull('subscription_id')
            ->when($this->selectedWeek, function ($query) {
                $query->where('week_start_date', $this->selectedWeek);
            })
            ->when($this->activeTab !== 'all', function ($query) {
                $query->where('designer_id', $this->activeTab);
            })
            ->orderBy('designer_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * يقوم بتنفيذ التوزيع التلقائي للمصممين.
     *
     * @return void
     */
    protected function performAutoDistribution(): void
    {
        try {
            $service = new DesignerDistributionService();
            $result = $service->autoDistribute($this->selectedWeek);

            if ($result['success']) {
                Notification::make()
                    ->title('نجح التوزيع التلقائي!')
                    ->body($result['message'])
                    ->success()
                    ->duration(5000)
                    ->send();

                // تحديث الإحصائيات
                $this->distributionStats = $result['statistics'];
            } else {
                Notification::make()
                    ->title('فشل التوزيع التلقائي')
                    ->body($result['message'])
                    ->danger()
                    ->duration(5000)
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('حدث خطأ')
                ->body('حدث خطأ أثناء التوزيع: ' . $e->getMessage())
                ->danger()
                ->duration(5000)
                ->send();
        }
    }

    /**
     * يقوم بمسح توزيعات الأسبوع المحدد.
     *
     * @return void
     */
    protected function clearDistribution(): void
    {
        try {
            $deleted = ClientDesigner::where('week_start_date', $this->selectedWeek)->delete();

            Notification::make()
                ->title('تم مسح التوزيع')
                ->body("تم حذف {$deleted} تعيين بنجاح")
                ->success()
                ->send();

            $this->distributionStats = null;
        } catch (\Exception $e) {
            Notification::make()
                ->title('حدث خطأ')
                ->body('حدث خطأ أثناء مسح التوزيع: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * يقوم بعرض تقرير التوزيع.
     *
     * @return void
     */
    protected function viewDistributionReport(): void
    {
        try {
            $service = new DesignerDistributionService();
            $report = $service->getDistributionReport($this->selectedWeek);

            $message = "تقرير التوزيع للأسبوع: {$report['week_start']}\n\n";
            $message .= "إجمالي التعيينات: {$report['total_assignments']}\n\n";

            foreach ($report['designers'] as $designerData) {
                $message .= "المصمم: {$designerData['designer_name']}\n";
                $message .= "عدد العملاء: {$designerData['clients_count']}\n";
                $message .= "إجمالي التصاميم: {$designerData['total_designs']}\n\n";
            }

            Notification::make()
                ->title('تقرير التوزيع')
                ->body($message)
                ->info()
                ->duration(10000)
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('حدث خطأ')
                ->body('حدث خطأ أثناء إنشاء التقرير: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * يقوم بتحميل إحصائيات التوزيع.
     *
     * @return void
     */
    protected function loadDistributionStats(): void
    {
        $service = new DesignerDistributionService();
        $report = $service->getDistributionReport($this->selectedWeek);
        $this->distributionStats = $report;
    }

    /**
     * يقوم بإرجاع ودجات (Widgets) رأس الصفحة.
     *
     * @return array
     */
    /**
     * الحصول على التبويبات المتاحة (المصممين).
     *
     * @return array
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => 'الكل',
        ];

        // جلب المصممين الذين لديهم تعيينات في الأسبوع الحالي مع الاشتراكات
        $assignments = ClientDesigner::where('week_start_date', $this->selectedWeek)
            ->with(['designer.user', 'subscription'])
            ->get();

        // تجميع حسب المصمم وحساب عدد التصاميم
        $designerStats = $assignments->groupBy('designer_id')->map(function ($designerAssignments) {
            $designer = $designerAssignments->first()->designer;
            // حساب مجموع التصاميم من الاشتراكات
            $totalDesigns = $designerAssignments->sum(function ($assignment) {
                return $assignment->subscription->designs_count ?? 0;
            });

            return [
                'name' => $designer->user->name ?? 'غير معروف',
                'count' => $totalDesigns
            ];
        });

        // ترتيب المصممين حسب الاسم (اختياري)
        $designerStats = $designerStats->sortBy('name');

        foreach ($designerStats as $designerId => $stats) {
            // عرض الاسم مع عدد التصاميم
            $tabs[$designerId] = "{$stats['name']} ({$stats['count']})";
        }

        return $tabs;
    }

    /**
     * يقوم بإرجاع ودجات (Widgets) رأس الصفحة.
     *
     * @return array
     */
    protected function getHeaderWidgets(): array
    {
        return [
            // يمكن إضافة widgets للإحصائيات هنا
        ];
    }

    /**
     * الانتقال إلى الأسبوع السابق.
     *
     * @return void
     */
    public function goToPreviousWeek(): void
    {
        $currentWeek = Carbon::parse($this->selectedWeek);
        $previousWeek = $currentWeek->subWeek()->startOfWeek()->format('Y-m-d');

        $this->selectedWeek = $previousWeek;
        $this->distributionStats = null; // Clear old stats
        $this->loadDistributionStats();
        $this->resetTable();
        $this->dispatch('$refresh');

        Notification::make()
            ->title('تم الانتقال إلى الأسبوع السابق')
            ->body("الأسبوع: {$previousWeek}")
            ->info()
            ->send();
    }

    /**
     * الانتقال إلى الأسبوع التالي.
     *
     * @return void
     */
    public function goToNextWeek(): void
    {
        $currentWeek = Carbon::parse($this->selectedWeek);
        $nextWeek = $currentWeek->addWeek()->startOfWeek()->format('Y-m-d');

        $this->selectedWeek = $nextWeek;
        $this->distributionStats = null; // Clear old stats
        $this->loadDistributionStats();
        $this->resetTable();
        $this->dispatch('$refresh');

        Notification::make()
            ->title('تم الانتقال إلى الأسبوع التالي')
            ->body("الأسبوع: {$nextWeek}")
            ->info()
            ->send();
    }

    /**
     * الانتقال إلى الأسبوع الحالي.
     *
     * @return void
     */
    public function goToCurrentWeek(): void
    {
        $currentWeek = Carbon::now()->startOfWeek()->format('Y-m-d');

        $this->selectedWeek = $currentWeek;
        $this->distributionStats = null; // Clear old stats
        $this->loadDistributionStats();
        $this->resetTable();
        $this->dispatch('$refresh');

        Notification::make()
            ->title('تم الانتقال إلى الأسبوع الحالي')
            ->body("الأسبوع: {$currentWeek}")
            ->success()
            ->send();
    }
}
