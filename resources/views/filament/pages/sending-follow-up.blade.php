<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-4 mb-4">
                <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-xl text-orange-600">
                    <x-heroicon-o-paper-airplane class="w-8 h-8" />
                </div>
                <div>
                     <h2 class="text-xl font-bold text-gray-900 dark:text-white">قائمة الإرسال والمتابعة</h2>
                     <p class="text-gray-500 dark:text-gray-400">التصاميم الجاهزة التي يجب إرسالها للعملاء حسب الجدول الزمني.</p>
                </div>
            </div>
            
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
