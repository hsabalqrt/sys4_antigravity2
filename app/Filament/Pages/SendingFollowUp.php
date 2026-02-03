<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\ClientTagDistribution;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class SendingFollowUp extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationLabel = 'واجهة الإرسال';
    protected static ?string $title = ' ';
    // protected static ?string $title = 'واجهة الإرسال والمتابعة';
    protected static ?int $navigationSort = 3;
    protected static bool $shouldRegisterNavigation = false; // Hidden from sidebar, accessed via Supervisor Dashboard

    protected static string $view = 'filament.pages.sending-follow-up';

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('supervisor') || auth()->user()->can('view_supervisor_dashboard') || auth()->user()->can('view_any_social_media');
    }


    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'الرئيسية',
            SupervisorDashboard::getUrl() => 'واجهة المشرف',
            static::getUrl() => 'واجهة الإرسال والمتابعة',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ClientTagDistribution::query()
                    ->where('status', 'sending')
                    ->with(['clientDesigner.client', 'clientDesigner.designer', 'tag', 'idea'])
                    ->orderBy('scheduled_sending_at', 'asc') // Urgency first
            )
            ->columns([
                TextColumn::make('clientDesigner.client.company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->clientDesigner->client->code),

                // TextColumn::make('clientDesigner.designer.user.name')
                //     ->label('المصمم')
                //     ->sortable(),

                TextColumn::make('tag.name')
                    ->label('التاق')
                    ->badge()
                    ->color('info'),
                
                TextColumn::make('idea.name')
                    ->label('الفكرة')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->idea?->content ?? $record->custom_idea),

                ImageColumn::make('attachment_path')
                    ->label('التصميم')
                    ->disk('public')
                    ->checkFileExistence(false)
                    ->visibility('public') 
                    ->width(80)
                    ->height(80),

                TextColumn::make('scheduled_sending_at')
                    ->label('وقت الإرسال المجدول')
                    ->dateTime('l, d M - h:i A')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state < now() ? 'danger' : 'success'),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('timeframe')
                    ->label('الفترة الزمنية')
                    ->options([
                        'today' => 'اليوم',
                        'overdue' => 'متأخرة (فائتة)',
                        'upcoming' => 'قادمة (مستقبلية)',
                        'all' => 'الكل',
                    ])
                    ->default('today')
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'];
                        if ($value === 'today') {
                            $query->whereDate('scheduled_sending_at', \Carbon\Carbon::today());
                        } elseif ($value === 'overdue') {
                            $query->where('scheduled_sending_at', '<', \Carbon\Carbon::today());
                        } elseif ($value === 'upcoming') {
                            $query->whereDate('scheduled_sending_at', '>', \Carbon\Carbon::today());
                        }
                        // 'all' passes through without filtering
                    }),
            ])
            ->actions([
                Action::make('markAsCompleted')
                    ->label('تم الإرسال للعميل')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    // ->requiresConfirmation()
                    ->action(function (ClientTagDistribution $record) {
                        $record->update([
                            'status' => 'completed',
                            'sender_id' => auth()->user()->id
                        ]);
                        Notification::make()
                            ->title('تم تحديث الحالة إلى "تم الإرسال"')
                            ->success()
                            ->send();
                    }),
                    
                Action::make('download')
                    ->label('تحميل')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (ClientTagDistribution $record) => \Illuminate\Support\Facades\Storage::disk('public')->download($record->attachment_path)),
            ])->bulkActions([
                Tables\Actions\BulkAction::make('downloadSelected')
                    ->label('تنزيل المحدد (ZIP)')
                    ->icon('heroicon-m-archive-box-arrow-down')
                    ->color('gray')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $zipFileName = 'designs-' . now()->timestamp . '.zip';
                        $zipPath = \Illuminate\Support\Facades\Storage::disk('public')->path($zipFileName);

                        $zip = new \ZipArchive;
                        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
                            foreach ($records as $record) {
                                if ($record->attachment_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($record->attachment_path)) {
                                    $filePath = \Illuminate\Support\Facades\Storage::disk('public')->path($record->attachment_path);
                                    // Construct meaningful filename: Client_Designer_OriginalName
                                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                                    $safeClient = \Illuminate\Support\Str::slug($record->clientDesigner->client->company ?? 'client', '_');
                                    $safeIdea = \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($record->idea->name ?? 'idea', 20), '_');
                                    $fileNameInZip = "{$safeClient}_{$safeIdea}_{$record->id}.{$extension}";
                                    
                                    $zip->addFile($filePath, $fileNameInZip);
                                }
                            }
                            $zip->close();
                        }

                        return response()->download($zipPath)->deleteFileAfterSend();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->poll('30s'); 
    }
}
