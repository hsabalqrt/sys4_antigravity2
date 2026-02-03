<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\ClientTagDistribution;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Concerns\InteractsWithForms;

class ReviewerDashboard extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'لوحة المراجع';
    protected static ?string $title = 'لوحة المراجع';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.reviewer-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_reviewer_dashboard');
    }

    public function getViewData(): array
    {
        $todayStr = Carbon::now()->format('Y-m-d');
        
        // Base query for items under review
        $query = ClientTagDistribution::query()
            ->where('status', 'reviewing')
            ->with(['clientDesigner.client', 'clientDesigner.designer', 'tag', 'idea']);

        // Get all reviewing items
        $allReviewing = $query->get()->sortByDesc('updated_at');

        // Split into "Today" (based on distribution date) and "Previous"
        // OR should strictly follow "submission time" (updated_at)?
        // User asked: "Number of designs under review, by today and by previous days."
        // Usually implies the "Task Date" (distribution_date).
        // Let's stick to distribution_date for grouping as it aligns with the daily workflow.
        
        $todayItems = $allReviewing->filter(function($item) use ($todayStr) {
            return $item->distribution_date === $todayStr;
        });

        $previousItems = $allReviewing->filter(function($item) use ($todayStr) {
            return $item->distribution_date < $todayStr;
        });

        return [
            'todayItems' => $todayItems,
            'previousItems' => $previousItems,
            'todayCount' => $todayItems->count(),
            'previousCount' => $previousItems->count(),
        ];
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label('اعتماد')
            ->color('success')
            // ->requiresConfirmation()
            ->action(function (array $arguments) {
                /**
                 * Description: Handles the approval process by updating the record status and logging the reviewer ID.
                 * Inputs/Key Variables: $arguments (array containing 'id' of the record), auth()->id() (current user ID).
                 * Outputs/Effect: Updates ClientTagDistribution record status to 'sending' and sets reviewer_id. Sends success notification.
                 */
                $record = ClientTagDistribution::find($arguments['id']);
                if ($record) {
                    $record->update([
                        'status' => 'sending',
                        'reviewer_id' => auth()->id(),
                    ]);
                    Notification::make()->title('تم اعتماد التصميم بنجاح')->success()->send();
                }
            });
    }

    public function requestChangesAction(): Action
    {
        return Action::make('requestChanges')
            ->label('طلب تعديلات')
            ->color('danger')
            ->form([
                \Filament\Forms\Components\Textarea::make('reviewer_feedback')
                    ->label('ملاحظات التعديل')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, array $arguments) {
                $record = ClientTagDistribution::find($arguments['id']);
                if ($record) {
                    $record->update([
                        'status' => 'changes_requested',
                        'reviewer_feedback' => $data['reviewer_feedback']
                    ]);
                    Notification::make()->title('تم طلب التعديلات')->success()->send();
                }
            });
    }
}
