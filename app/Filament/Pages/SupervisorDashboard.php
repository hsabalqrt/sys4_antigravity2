<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\ClientTagDistribution;

/**
 * الوصف العام: صفحة واجهة المشرف لعرض الإحصائيات الأساسية للتصاميم.
 */
class SupervisorDashboard extends Page
{
    /**
     * @param: static ?string $navigationIcon
     * الوصف: الأيقونة المستخدمة في القائمة الجانبية.
     */
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    /**
     * @param: static ?string $navigationLabel
     * الوصف: النص المعروض في القائمة الجانبية.
     */
    protected static ?string $navigationLabel = 'واجهة المشرف';

    /**
     * @param: static ?string $title
     * الوصف: عنوان الصفحة المعروض في الأعلى.
     */
    protected static ?string $title = 'واجهة المشرف';

    /**
     * @param: static ?int $navigationSort
     * الوصف: ترتيب ظهور الصفحة في القائمة الجانبية.
     */
    protected static ?int $navigationSort = 1;

    /**
     * الوصف: التحقق من صلاحية الوصول للصفحة (يسمح للمشرفين فقط).
     * @returns: bool
     */
    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('supervisor') || auth()->user()->can('view_supervisor_dashboard');
    }

    /**
     * @param: static string $view
     * الوصف: ملف العرض (Blade) الخاص بالصفحة.
     */
    protected static string $view = 'filament.pages.supervisor-dashboard';

    /**
     * الوصف: تجميع البيانات اللازمة للعرض في الواجهة.
     * @returns: array
     */
    protected function getViewData(): array
    {
        /**
         * @var: completedCount - تم ارسالها للعميل
         * @var: sendingCount - جاهزة لارسالها الى العميل
         * @var: reviewingCount - انها قيد المراجعة
         * @var: pendingCount - قيد التنفيذ (لم تُجرد)
         */
        return [
            'completedCount' => ClientTagDistribution::where('status', 'completed')->count(),
            'sendingCount' => ClientTagDistribution::where('status', 'sending')->count(),
            'reviewingCount' => ClientTagDistribution::where('status', 'reviewing')->count(),
            'pendingCount' => ClientTagDistribution::where(function($query) {
                $query->whereIn('status', ['pending', 'in_progress'])
                      ->orWhereNull('status')
                      ->orWhere('status', '');
            })->count(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'الرئيسية',
            static::getUrl() => 'واجهة المشرف',
        ];
    }
}
