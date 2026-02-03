<x-filament-panels::page>
    {{-- 
        الوصف العام: واجهة المشرف لعرض إحصائيات التصاميم بناءً على حالات العمل الفعلية.
        المدخلات: $completedCount, $sendingCount, $reviewingCount, $pendingCount
        المخرجات: عرض 4 بطاقات إحصائية تعبر عن مراحل العمل.
    --}}
    <div class="space-y-8 font-sans" dir="rtl">
        
        <!-- Header Section -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#441188] via-[#5a1aa8] to-[#2d0b5e] text-white shadow-2xl p-8">
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden opacity-20 pointer-events-none">
                <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full bg-[#ff6600] blur-[100px]"></div>
                <div class="absolute bottom-0 left-20 w-48 h-48 rounded-full bg-[#ff6600] blur-[80px]"></div>
            </div>

            <div class="relative z-10">
                <h1 class="text-3xl font-bold mb-2 tracking-tight">واجهة الإشراف والمتابعة</h1>
                <p class="text-white/70 text-lg">متابعة دقيقة لكل فكرة من لحظة الجرد وحتى وصولها للعميل.</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Pending Designs Card (ما جردها / قيد التنفيذ) -->
            <div class="group relative overflow-hidden bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-xl transition-all duration-300">
                <div class="absolute top-0 right-0 w-1.5 h-full bg-gray-400"></div>
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-800 text-gray-500 group-hover:scale-110 transition-transform duration-300">
                        <x-heroicon-o-clock class="w-6 h-6" />
                    </div>
                </div>
                <div class="flex flex-col">
                    <span class="text-4xl font-black text-gray-900 dark:text-white mb-1 leading-none">{{ $pendingCount }}</span>
                    <span class="text-sm font-bold text-gray-500">قيد التنفيذ</span>
                </div>
            </div>

            <!-- Reviewing Designs Card (قيد المراجعة) -->
            <div class="group relative overflow-hidden bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-xl transition-all duration-300">
                <div class="absolute top-0 right-0 w-1.5 h-full bg-blue-500"></div>
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-2xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 group-hover:scale-110 transition-transform duration-300">
                        <x-heroicon-o-magnifying-glass-circle class="w-6 h-6" />
                    </div>
                </div>
                <div class="flex flex-col">
                    <span class="text-4xl font-black text-gray-900 dark:text-white mb-1 leading-none">{{ $reviewingCount }}</span>
                    <span class="text-sm font-bold text-blue-600">قيد المراجعة</span>
                </div>
            </div>

            <!-- Sending Designs Card (جاهزة للإرسال) -->
            <a href="{{ \App\Filament\Pages\SendingFollowUp::getUrl() }}" class="block group relative overflow-hidden bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-xl transition-all duration-300 hover:scale-[1.02] cursor-pointer">
                <div class="absolute top-0 right-0 w-1.5 h-full bg-[#ff6600]"></div>
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-2xl bg-[#ff6600]/10 text-[#ff6600] group-hover:scale-110 transition-transform duration-300">
                        <x-heroicon-o-paper-airplane class="w-6 h-6" />
                    </div>
                    <span class="bg-[#ff6600]/10 text-[#ff6600] text-xs px-2 py-1 rounded-lg font-bold">عرض القائمة &larr;</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-4xl font-black text-gray-900 dark:text-white mb-1 leading-none">{{ $sendingCount }}</span>
                    <span class="text-sm font-bold text-[#ff6600]">جاهزة للإرسال للعميل</span>
                </div>
            </a>

            <!-- Completed Designs Card (تم إرسالها) -->
            <div class="group relative overflow-hidden bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 p-6 shadow-sm hover:shadow-xl transition-all duration-300">
                <div class="absolute top-0 right-0 w-1.5 h-full bg-green-500"></div>
                <div class="flex items-start justify-between mb-4">
                    <div class="p-3 rounded-2xl bg-green-50 dark:bg-green-900/20 text-green-600 group-hover:scale-110 transition-transform duration-300">
                        <x-heroicon-o-check-badge class="w-6 h-6" />
                    </div>
                </div>
                <div class="flex flex-col">
                    <span class="text-4xl font-black text-gray-900 dark:text-white mb-1 leading-none">{{ $completedCount }}</span>
                    <span class="text-sm font-bold text-green-600">تم إرسالها للعميل</span>
                </div>
            </div>

        </div>

        <!-- Info Section -->
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-3xl p-6 border border-gray-100 dark:border-gray-700">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">دليل الحالات:</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 rounded-xl">
                    <span class="w-3 h-3 rounded-full bg-gray-400"></span>
                    <span class="text-gray-600 dark:text-gray-400 font-medium">قيد التنفيذ: التصاميم التي لم يتم تسليمها من المصمم بعد.</span>
                </div>
                <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 rounded-xl">
                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                    <span class="text-gray-600 dark:text-gray-400 font-medium">قيد المراجعة: تصاميم سلمها المصمم وتنتظر قرار المراجع.</span>
                </div>
                <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 rounded-xl">
                    <span class="w-3 h-3 rounded-full bg-[#ff6600]"></span>
                    <span class="text-gray-600 dark:text-gray-400 font-medium">جاهزة للإرسال: تم اعتمادها وبانتظار فريق السوشيال ميديا لإرسالها.</span>
                </div>
                <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 rounded-xl">
                    <span class="w-3 h-3 rounded-full bg-green-500"></span>
                    <span class="text-gray-600 dark:text-gray-400 font-medium">تم إرسالها للعميل: المهمة اكتملت تماماً ووصلت للعميل.</span>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
