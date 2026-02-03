<x-filament-panels::page>
    <div class="space-y-8 font-sans pb-12" dir="rtl">
        
        <!-- Header Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto w-full">
            <!-- Today Stats -->
            <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
                <div class="absolute top-0 left-0 p-4 opacity-20 transform -rotate-12">
                    <x-heroicon-o-calendar-days class="w-32 h-32" />
                </div>
                <div class="relative z-10">
                    <h3 class="text-lg font-bold opacity-90 mb-2">تصاميم اليوم للتوجيه</h3>
                    <div class="text-5xl font-black">{{ $todayCount }}</div>
                    <p class="text-sm opacity-75 mt-2">تنتظر المراجعة والاعتماد</p>
                </div>
            </div>

            <!-- Previous Stats -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 text-gray-800 dark:text-white shadow-lg border border-gray-100 dark:border-gray-700 relative overflow-hidden">
                <div class="absolute top-0 left-0 p-4 opacity-5 transform -rotate-12">
                    <x-heroicon-o-clock class="w-32 h-32" />
                </div>
                <div class="relative z-10">
                    <h3 class="text-lg font-bold opacity-90 mb-2">تصاميم سابقة متأخرة</h3>
                    <div class="text-5xl font-black text-orange-500">{{ $previousCount }}</div>
                     <p class="text-sm opacity-60 mt-2">من أيام سابقة</p>
                </div>
            </div>
        </div>

        <!-- Feed Content -->
        <div class="space-y-8 max-w-4xl mx-auto w-full">
            
            @foreach(['today' => 'واليوم', 'previous' => 'السابقة'] as $key => $label)
                @php
                    $items = $key === 'today' ? $todayItems : $previousItems;
                @endphp

                @if($items->isNotEmpty())
                    
                    @if($key === 'previous')
                         <div class="relative py-4">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-3 bg-gray-50 dark:bg-gray-900 text-gray-500 font-bold rounded-full">تصاميم فائتة</span>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-6">
                        @foreach($items as $item)
                             <!-- Feed Card -->
                            <div wire:key="review-item-{{ $item->id }}" 
                                 class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
                                
                                <!-- Header: User & Time -->
                                <div class="p-4 flex items-center gap-3">
                                    <!-- Avatar / Placeholder -->
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center shrink-0 border border-gray-200 dark:border-gray-600">
                                        <span class="text-xs font-bold text-gray-600 dark:text-gray-300">
                                            <!-- display user image -->
                                             @if($item->clientDesigner->designer->user->profile_image)
                                             <img src="{{ asset('storage/' . $item->clientDesigner->designer->user->profile_image) }}" alt="User Image" class="w-full h-full object-cover rounded-full">
                                             @else
                                             {{ Str::substr($item->clientDesigner->designer->user->name ?? '?', 0, 2) }}
                                             @endif
                                        </span>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-bold text-gray-900 dark:text-white truncate">
                                            {{ $item->clientDesigner->designer->user->name ?? 'مُصمم' }} 
                                            <span class="text-gray-400 font-normal mx-1">لـ</span>
                                            {{ $item->clientDesigner->client->company ?? 'عميل' }}
                                        </h4>
                                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span dir="ltr">{{ $item->updated_at->diffForHumans() }}</span>
                                            <span>•</span>
                                            <span class="px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-medium text-[10px]">
                                                {{ $item->tag->name ?? 'عام' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="shrink-0">
                                         <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                                            <x-heroicon-m-ellipsis-horizontal class="w-6 h-6" />
                                         </button>
                                    </div>
                                </div>

                                <!-- Post Notes (if short) or Description -->
                                @if($item->idea || $item->designer_notes || $item->custom_idea)
                                    <div class="px-4 pb-3 space-y-3" dir="rtl">
                                        
                                        {{-- Idea Section --}}
                                        @if($item->idea)
                                            <div class="bg-indigo-50 dark:bg-indigo-900/20 p-3 rounded-lg border border-indigo-100 dark:border-indigo-800/30">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <x-heroicon-m-light-bulb class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                                    <span class="font-bold text-indigo-800 dark:text-indigo-300 text-sm">الفكرة: {{ $item->idea->name }}</span>
                                                </div>
                                                
                                                @if($item->idea->description)
                                                     <p class="text-sm text-gray-700 dark:text-gray-300 mb-3 leading-relaxed opacity-90">{{ $item->idea->description }}</p>
                                                @endif
                                
                                                @if($item->idea->content)
                                                    <div class="bg-white dark:bg-gray-900/50 rounded p-2.5 border border-indigo-100 dark:border-indigo-800/30 mt-2">
                                                        <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider mb-1.5 block">محتوى الفكرة المطلوب</span>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line leading-relaxed">{{ $item->idea->content }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        @elseif($item->custom_idea)
                                             <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg border border-orange-100 dark:border-orange-800/30">
                                                <div class="flex items-center gap-2 mb-1">
                                                     <x-heroicon-m-sparkles class="w-4 h-4 text-orange-500" />
                                                     <span class="font-bold text-orange-800 dark:text-orange-300 text-sm">فكرة مخصصة</span>
                                                </div>
                                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $item->custom_idea }}</p>
                                            </div>
                                        @endif
                                
                                        {{-- Designer Notes --}}
                                        @if($item->designer_notes)
                                            <div class="flex gap-3 items-start bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg border border-gray-100 dark:border-gray-700">
                                                <div class="mt-0.5 shrink-0">
                                                    <x-heroicon-m-pencil-square class="w-4 h-4 text-gray-400" />
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                     <span class="text-xs font-bold text-gray-500 block mb-1">ملاحظات المصمم</span>
                                                     <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ $item->designer_notes }}</p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <!-- Main Media (Full Width) -->
                                <div class="bg-gray-100 dark:bg-black/50 overflow-hidden relative group">
                                     @if($item->attachment_path)
                                        <a href="{{ Storage::url($item->attachment_path) }}" target="_blank" class="block w-full">
                                            <img src="{{ Storage::url($item->attachment_path) }}" 
                                                 class="w-full h-auto max-h-[600px] object-contain mx-auto bg-gray-50 dark:bg-gray-900" 
                                                 alt="Design Preview" 
                                                 loading="lazy" />
                                        </a>
                                    @else
                                        <div class="h-64 flex flex-col items-center justify-center text-gray-400 gap-2">
                                            <x-heroicon-o-photo class="w-12 h-12 opacity-50" />
                                            <span>لا يوجد صورة مرفقة</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Action Buttons (Facebook Style) -->
                                <div class="p-2 border-t border-gray-100 dark:border-gray-800 grid grid-cols-2 gap-2">
                                    <button wire:click="mountAction('approve', { id: {{ $item->id }} })"
                                            class="flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 text-gray-600 dark:text-gray-300 hover:text-green-600 dark:hover:text-green-400 transition-colors group/btn">
                                        <x-heroicon-o-hand-thumb-up class="w-6 h-6 group-hover/btn:scale-110 transition-transform" />
                                        <span class="font-bold">اعتماد</span>
                                    </button>

                                    <button wire:click="mountAction('requestChanges', { id: {{ $item->id }} })"
                                            class="flex items-center justify-center gap-2 py-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-600 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 transition-colors group/btn">
                                        <x-heroicon-o-chat-bubble-left-ellipsis class="w-6 h-6 group-hover/btn:scale-110 transition-transform" />
                                        <span class="font-bold">طلب تعديل</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach

            @if($todayItems->isEmpty() && $previousItems->isEmpty())
                <div class="text-center py-20 text-gray-400">
                    <x-heroicon-o-check-badge class="w-20 h-20 mx-auto mb-4 opacity-50" />
                    <h2 class="text-xl font-bold">لا توجد تصاميم للمراجعة حالياً</h2>
                    <p>جميع المهام تم انجازها أو لم يتم تسليمها بعد</p>
                </div>
            @endif

        </div>
    </div>
</x-filament-panels::page>
