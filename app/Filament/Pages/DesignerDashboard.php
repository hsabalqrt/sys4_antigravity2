<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class DesignerDashboard extends Page
{
    /**
     * أيقونة الصفحة في القائمة الجانبية.
     * @var string|null
     */
    use \Filament\Actions\Concerns\InteractsWithActions;
    use \Filament\Forms\Concerns\InteractsWithForms;

    /**
     * أيقونة الصفحة في القائمة الجانبية.
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    /**
     * عنوان الصفحة في القائمة الجانبية.
     * @var string|null
     */
    protected static ?string $navigationLabel = 'لوحة المصمم';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_designer_dashboard');
    }

    /**
     * عنوان الصفحة.
     * @var string|null
     */
    protected static ?string $title = 'لوحة المصمم';

    /**
     * @var string|null
     */
    public ?string $filterDate = null;

    public function mount()
    {
        $this->filterDate = \Carbon\Carbon::now()->format('Y-m-d');
    }

    /**
     * اسم العرض الخاص بالصفحة.
     * @var string
     */
    protected static string $view = 'filament.pages.designer-dashboard';

    /**
     * الحصول على البيانات التي يتم تمريرها إلى العرض.
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $designer = \App\Models\Designer::where('user_id', $user->id)->first();

        // Use the selected date or default to now
        $targetDate = $this->filterDate ? \Carbon\Carbon::parse($this->filterDate) : \Carbon\Carbon::now();
        $weekStartDate = $targetDate->copy()->startOfWeek()->format('Y-m-d');

        $assignments = [];

        if ($designer) {
            $assignments = \App\Models\ClientDesigner::query()
                ->where('designer_id', $designer->id)
                ->where('week_start_date', $weekStartDate)
                ->with([
                    'client',
                    'distributions' => function ($query) {
                        $query->orderBy('distribution_date');
                    },
                    'distributions.tag',
                    'distributions.idea'
                ])
                ->get();
            
            // Stats Calculation
            $baseQuery = \App\Models\ClientTagDistribution::query()
                ->whereHas('clientDesigner', function($q) use ($designer) {
                    $q->where('designer_id', $designer->id);
                });

            $targetDateStr = $targetDate->format('Y-m-d');

            // Helper for pending status (pending, in_progress, null, or empty)
            $pendingCallback = function($q) {
                $q->whereIn('status', ['pending', 'in_progress'])
                  ->orWhereNull('status')
                  ->orWhere('status', '');
            };

            $stats['todayPending'] = (clone $baseQuery)
                ->where('distribution_date', $targetDateStr)
                ->where($pendingCallback)
                ->count();

            $stats['todayChanges'] = (clone $baseQuery)
                ->where('distribution_date', $targetDateStr)
                ->where('status', 'changes_requested')
                ->count();

            $stats['totalPending'] = (clone $baseQuery)
                ->where('distribution_date', '<=', \Carbon\Carbon::now()->format('Y-m-d'))
                ->where($pendingCallback)
                ->count();

            $stats['totalChanges'] = (clone $baseQuery)
                ->where('distribution_date', '<=', \Carbon\Carbon::now()->format('Y-m-d'))
                ->where('status', 'changes_requested')
                ->count();
        } else {
             $stats = [
                'todayPending' => 0,
                'todayChanges' => 0,
                'totalPending' => 0,
                'totalChanges' => 0,
            ];
        }

        return [
            'designer' => $designer,
            'user' => $user,
            'assignments' => $assignments,
            'weekStartDate' => $weekStartDate,
            'currentDate' => $targetDate->format('Y-m-d'),
            'stats' => $stats,
        ];
    }

    /**
     * تحديد مسار التنقل (Breadcrumbs) للصفحة.
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'الرئيسية',
            static::getUrl() => 'لوحة المصمم',
        ];
    }

    /**
     * تحديث حالة المهمة (simple status update without modal).
     */
    public function updateTaskStatus($distributionId, $status)
    {
        $distribution = \App\Models\ClientTagDistribution::find($distributionId);
        
        if ($distribution) {
            $distribution->update(['status' => $status]);
            
            \Filament\Notifications\Notification::make()
                ->title('تم تحديث حالة المهمة')
                ->success()
                ->send();
        }
    }

    public function submitTaskAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('submitTask')
            ->label('تسليم المهمة')
            ->modalHeading('تسليم المهمة للمراجعة')
            ->form([
                \Filament\Forms\Components\FileUpload::make('attachment_path')
                    ->label('الصورة النهائية (إجباري)')
                    ->image()
                    ->directory('designer-submissions')
                    ->maxSize(2048)
                    ->required(),
                \Filament\Forms\Components\Textarea::make('designer_notes')
                    ->label('ملاحظات (اختياري)')
                    ->rows(3),
            ])
            ->action(function (array $data, array $arguments) {
                $record = \App\Models\ClientTagDistribution::find($arguments['distribution_id']);
                if ($record) {
                    $record->update([
                        'status' => 'reviewing',
                        'attachment_path' => $data['attachment_path'],
                        'designer_notes' => $data['designer_notes'],
                    ]);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('تم إرسال المهمة للمراجعة 🚀')
                        ->success()
                        ->send();
                }
            });
    }

    public function editTaskAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('editTask')
            ->label('تعديل التسليم')
            ->modalHeading('تعديل التسليم')
            ->fillForm(function (array $arguments) {
                $record = \App\Models\ClientTagDistribution::find($arguments['distribution_id']);
                return [
                    'attachment_path' => $record?->attachment_path,
                    'designer_notes' => $record?->designer_notes,
                ];
            })
            ->form([
                \Filament\Forms\Components\FileUpload::make('attachment_path')
                    ->label('استبدال الصورة (اختياري)')
                    ->image()
                    ->disk('public')
                    ->directory('designer-submissions'),
                \Filament\Forms\Components\Textarea::make('designer_notes')
                    ->label('تعديل الملاحظات')
                    ->rows(3),
            ])
            ->action(function (array $data, array $arguments) {
                $record = \App\Models\ClientTagDistribution::find($arguments['distribution_id']);
                if ($record) {
                    $updateData = ['designer_notes' => $data['designer_notes']];
                    // Only update attachment if a new one is uploaded
                    if (!empty($data['attachment_path'])) {
                         $updateData['attachment_path'] = $data['attachment_path'];
                    }

                    $record->update($updateData);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('تم تحديث التسليم بنجاح')
                        ->success()
                        ->send();
                }
            });
    }
}
