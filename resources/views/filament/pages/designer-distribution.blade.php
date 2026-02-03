<x-filament-panels::page>
    <div class="space-y-6">
        {{-- نموذج اختيار الأسبوع --}}
        <x-filament::section>


            {{-- أزرار التنقل بين الأسابيع --}}
            <div class="mb-4 flex flex-wrap items-center justify-center gap-3">
                {{-- زر الأسبوع السابق --}}
                <x-filament::button
                    wire:click="goToPreviousWeek"
                    icon="heroicon-o-chevron-right"
                    icon-position="before"
                    color="secondary">
                    الأسبوع السابق
                </x-filament::button>

                {{-- زر الأسبوع الحالي --}}
                <x-filament::button
                    wire:click="goToCurrentWeek"
                    left-icon="heroicon-o-calendar"
                    color="primary"
                    size="lg">
                    الأسبوع الحالي
                </x-filament::button>

                {{-- زر الأسبوع التالي --}}
                <x-filament::button
                    wire:click="goToNextWeek"
                    icon="heroicon-o-chevron-left"
                    icon-position="after"
                    color="secondary">
                    الأسبوع التالي
                </x-filament::button>
            </div>

            {{-- عرض الأسبوع الحالي --}}
            @if($selectedWeek)
            <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-purple dark:border-gray-800 rounded-lg">
                <div class="flex items-center justify-center gap-2 text-sm">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-blue-700 dark:text-blue-300 font-medium">
                        الأسبوع المحدد:
                        <span class="font-bold">{{ \Carbon\Carbon::parse($selectedWeek)->format('Y-m-d') }}</span>
                        @if(\Carbon\Carbon::parse($selectedWeek)->startOfWeek()->isSameWeek(\Carbon\Carbon::now()->startOfWeek()))
                        <span class="ml-2 px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-xs rounded-full">
                            الأسبوع الحالي
                        </span>
                        @endif
                    </span>
                </div>
            </div>
            @endif
        </x-filament::section>

        {{-- جدول التوزيع --}}
        <x-filament::section>
            <x-slot name="heading">
                التوزيع الحالي
            </x-slot>

            <x-slot name="headerEnd">
                <x-filament::tabs label="التبويبات" class="border-b-0">
                    @foreach($this->getTabs() as $key => $label)
                        <x-filament::tabs.item
                            :active="$activeTab == (string) $key"
                            wire:click="$set('activeTab', '{{ $key }}')"
                        >
                            {{ $label }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            </x-slot>

            <x-slot name="description">
                عرض وإدارة توزيع العملاء على المصممين للأسبوع المحدد
            </x-slot>

            {{ $this->table }}
        </x-filament::section>


        {{-- إحصائيات التوزيع --}}
        @if($distributionStats)
        <x-filament::section>
            <x-slot name="heading">
                إحصائيات التوزيع
            </x-slot>

            <x-slot name="description">
                إحصائيات التوزيع
            </x-slot>



            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- العدادات السابقة --}}
                <div class="bg-info-50 dark:bg-info-900/20 p-4 rounded-lg border border-info-200 dark:border-info-800">
                    <div class="text-sm text-info-600 dark:text-info-400 font-medium">عدد المصممين المستخدمين</div>
                    <div class="text-2xl font-bold text-info-900 dark:text-info-100 mt-1">
                        {{ $distributionStats['total_designers_used'] ?? 0 }}
                    </div>
                </div>


                <div class="bg-warning-50 dark:bg-warning-900/20 p-4 rounded-lg border border-warning-200 dark:border-warning-800">
                    <div class="text-sm text-warning-600 dark:text-warning-400 font-medium">متوسط العملاء لكل مصمم</div>
                    <div class="text-2xl font-bold text-warning-900 dark:text-warning-100 mt-1">
                        @php
                        $totalDesigners = $distributionStats['total_designers_used'] ?? 1;
                        $totalAssignments = $distributionStats['total_assignments'] ?? 0;
                        $average = $totalDesigners > 0 ? round($totalAssignments / $totalDesigners, 1) : 0;
                        @endphp
                        {{ $average }}
                    </div>
                </div>
            </div>

            {{-- تفاصيل المصممين --}}
            @if(isset($distributionStats['designers']) && count($distributionStats['designers']) > 0)
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-4">تفاصيل توزيع المصممين</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($distributionStats['designers'] as $designerId => $designerData)
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ $designerData['designer_name'] ?? 'غير معروف' }}
                            </h4>
                            <span class="text-xs bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200 px-2 py-1 rounded">
                                مصمم
                            </span>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">عدد العملاء:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $designerData['clients_count'] ?? 0 }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">إجمالي التصاميم:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $designerData['total_designs'] ?? 0 }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </x-filament::section>
        @endif



        {{-- معلومات إضافية --}}

    </div>
</x-filament-panels::page>