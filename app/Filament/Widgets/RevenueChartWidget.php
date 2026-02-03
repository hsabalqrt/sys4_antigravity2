<?php

// namespace App\Filament\Widgets;

// use App\Models\Transaction;
// use Filament\Widgets\ChartWidget;
// use Illuminate\Support\Carbon;

// class RevenueChartWidget extends ChartWidget
// {
//     protected static ?string $heading = 'الإيرادات الشهرية (آخر 12 شهر)';
//     protected static ?int $sort = 2;
//     protected int | string | array $columnSpan = 'full';

//     protected function getData(): array
//     {
//         $data = Transaction::selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(amount) as total")
//             ->where('type', 'credit') // Only income
//             ->where('transaction_date', '>=', now()->subMonths(12))
//             ->groupBy('month')
//             ->orderBy('month')
//             ->get();

//         return [
//             'datasets' => [
//                 [
//                     'label' => 'الإيرادات (YER)',
//                     'data' => $data->pluck('total')->toArray(),
//                     'fill' => 'start',
//                     'borderColor' => '#3b82f6',
//                     'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
//                 ],
//             ],
//             'labels' => $data->pluck('month')->toArray(),
//         ];
//     }

//     protected function getType(): string
//     {
//         return 'line';
//     }
// }
