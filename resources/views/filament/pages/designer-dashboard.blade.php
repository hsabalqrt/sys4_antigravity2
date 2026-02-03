<x-filament-panels::page>
    <div class="space-y-12 font-sans" dir="rtl" x-data="{ activeIdea: null }">
        
        <!-- Dashboard Header -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#441188] via-[#5a1aa8] to-[#2d0b5e] text-white shadow-2xl">
            <!-- Decorative Background Elements -->
             <div class="absolute top-0 left-0 w-full h-full overflow-hidden opacity-20 pointer-events-none">
                <div class="absolute -top-24 -right-24 w-64 h-64 rounded-full bg-[#ff6600] blur-[100px]"></div>
                <div class="absolute bottom-0 left-20 w-48 h-48 rounded-full bg-[#ff6600] blur-[80px]"></div>
            </div>

            <div class="relative z-10 p-8 flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <!-- <h1 class="text-3xl md:text-4xl font-bold mb-2 tracking-tight">
                        لوحة المصمم
                    </h1> -->
                    <p class="text-white text-3xl font-medium">
                        أهلاً بك <span class="text-[#ff9955] font-bold">{{ $user->name }}</span>
                    </p>
                </div>
                
                <div class="hidden md:flex gap-4">
                     <!-- Today Stats -->
                    <div class="group relative overflow-hidden bg-white/10 hover:bg-white/15 backdrop-blur-md border border-white/20 p-4 rounded-2xl flex flex-col justify-between min-w-[180px] transition-all duration-300">
                        <div class="absolute top-0 left-0 p-2 opacity-10 group-hover:opacity-20 transition-opacity transform -rotate-12 translate-x-2 -translate-y-2">
                             <x-heroicon-o-calendar-days class="w-16 h-16 text-white" />
                        </div>
                        <span class="text-white/80 text-xs font-bold mb-3 z-10 uppercase tracking-wider">إحصائيات اليوم</span>
                        <div class="flex items-end gap-5 z-10">
                            <div class="flex flex-col">
                                <span class="text-2xl font-black text-white leading-none mb-1">{{ $stats['todayPending'] }}</span>
                                <span class="text-[10px] text-white/60 font-medium">قيد الانتظار</span>
                            </div>
                            <div class="w-px h-8 bg-gradient-to-b from-white/0 via-white/20 to-white/0"></div>
                            <div class="flex flex-col">
                                <span class="text-2xl font-black text-[#ff9955] leading-none mb-1">{{ $stats['todayChanges'] }}</span>
                                <span class="text-[10px] text-white/50 font-medium">تعديلات</span>
                            </div>
                        </div>
                    </div>

                    <!-- Total Stats -->
                    <div class="group relative overflow-hidden bg-white/10 hover:bg-white/15 backdrop-blur-md border border-white/20 p-4 rounded-2xl flex flex-col justify-between min-w-[180px] transition-all duration-300">
                        <div class="absolute top-0 left-0 p-2 opacity-5 group-hover:opacity-10 transition-opacity transform -rotate-12 translate-x-2 -translate-y-2">
                             <x-heroicon-o-chart-bar-square class="w-16 h-16 text-white" />
                        </div>
                        <span class="text-white/50 text-xs font-bold mb-3 z-10 uppercase tracking-wider">إجمالي المهام</span>
                        <div class="flex items-end gap-5 z-10">
                            <div class="flex flex-col">
                                <span class="text-2xl font-black text-gray-300 leading-none mb-1">{{ $stats['totalPending'] }}</span>
                                <span class="text-[10px] text-white/30 font-medium">قيد الانتظار</span>
                            </div>
                            <div class="w-px h-8 bg-gradient-to-b from-white/0 via-white/10 to-white/0"></div>
                             <div class="flex flex-col">
                                <span class="text-2xl font-black text-red-400 leading-none mb-1">{{ $stats['totalChanges'] }}</span>
                                <span class="text-[10px] text-white/30 font-medium">تعديلات</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white/10 backdrop-blur-md border border-white/20 p-4 rounded-xl flex flex-col items-center min-w-[140px]">
                    <span class="text-white/60 text-sm font-medium mb-1">
                        {{ \Carbon\Carbon::parse($filterDate)->locale('ar')->dayName }}
                    </span>
                    <input type="date" wire:model.live="filterDate" class="bg-transparent border-0 text-white text-xl font-bold font-mono tracking-wide p-0 text-center focus:ring-0 [&::-webkit-calendar-picker-indicator]:invert cursor-pointer w-full" />
                </div>
            </div>
        </div>

        @if(!$designer)
            <div class="rounded-xl bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 p-6 flex flex-col md:flex-row items-center gap-6 text-center md:text-right">
                <div class="bg-red-100 dark:bg-red-900/30 p-4 rounded-full">
                     <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <h3 class="font-bold text-lg text-red-700 dark:text-red-400 mb-1">حساب غير مرتبط</h3>
                    <p class="text-red-600/80 dark:text-red-400/70">يرجى التواصل مع الإدارة لربط حسابك بملف مصمم.</p>
                </div>
            </div>
        @else
            @php
                // $currentDate is passed from the component
                $dateString = $currentDate;
                
                $allTasks = collect();
                foreach($assignments as $assignment) {
                    foreach($assignment->distributions as $dist) {
                        if($dist->distribution_date == $dateString) {
                             $allTasks->push([
                                'client' => $assignment->client,
                                'distribution' => $dist,
                                'status' => strtolower(trim($dist->status ?? 'pending'))
                             ]);
                        }
                    }
                }
                
                // Define Groups and their Priority
                $groups = [
                    'changes_requested' => [
                        'label' => '⚠️ مطلوب تعديل',
                        'description' => 'المهام التي تم إرجاعها من قبل المراجع للتصحيح',
                        'color' => 'red',
                        'icon' => 'heroicon-o-exclamation-circle'
                    ],
                    'pending' => [
                        'label' => '📋 قيد الانتظار',
                        'description' => 'المهام الجديدة التي يجب البدء بها وتسليمها',
                        'color' => 'gray',
                        'icon' => 'heroicon-o-clock'
                    ],
                    'reviewing' => [
                        'label' => '👀 قيد المراجعة',
                        'description' => 'المهام التي تم تسليمها وتنتظر الاعتماد',
                        'color' => 'blue',
                        'icon' => 'heroicon-o-eye'
                    ],
                    'sending' => [
                        'label' => '🚀 قيد الإرسال',
                        'description' => 'المهام الجاهزة للإرسال للعميل',
                        'color' => 'indigo',
                        'icon' => 'heroicon-o-paper-airplane'
                    ],
                    'completed' => [
                        'label' => '✅ تم الإنجاز',
                        'description' => 'المهام المنتهية والمعتمدة بشكل نهائي',
                        'color' => 'green',
                        'icon' => 'heroicon-o-check-badge'
                    ],
                ];

                $groupedTasks = $allTasks->groupBy('status');
                
                // Merge 'in_progress' and empty into 'pending'
                $pending = $groupedTasks->get('pending', collect());
                if($groupedTasks->has('in_progress')) {
                    $pending = $pending->merge($groupedTasks->get('in_progress'));
                    $groupedTasks->forget('in_progress');
                }
                if($groupedTasks->has('')) {
                    $pending = $pending->merge($groupedTasks->get(''));
                    $groupedTasks->forget('');
                }
                $groupedTasks->put('pending', $pending);

                // Identify Uncategorized (Any status not in our groups)
                $knownKeys = collect($groups)->keys();
                $unknownKeys = $groupedTasks->keys()->diff($knownKeys);
                
                if($unknownKeys->isNotEmpty()) {
                    $uncategorized = collect();
                    foreach($unknownKeys as $key) {
                        $uncategorized = $uncategorized->merge($groupedTasks->get($key));
                        // $groupedTasks->forget($key); // Optional: remove from main list if strict
                    }
                    $groupedTasks->put('uncategorized', $uncategorized);
                    
                    // Add config for uncategorized
                    $groups['uncategorized'] = [
                        'label' => '❓ غير مصنف',
                        'description' => 'مهام بحاجة لتصحيح الحالة',
                        'color' => 'gray',
                        'icon' => 'heroicon-o-question-mark-circle'
                    ];
                }
            @endphp

            @if($allTasks->isEmpty())
                <div class="py-20 flex flex-col items-center justify-center text-center">
                    <div class="w-32 h-32 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-6">
                        <x-heroicon-o-check-badge class="w-16 h-16 text-gray-400" />
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">لا توجد مهام اليوم!</h2>
                </div>
            @else
                
                @foreach($groups as $groupKey => $groupConfig)
                    @if($groupedTasks->has($groupKey) && $groupedTasks[$groupKey]->isNotEmpty())
                        
                        <div class="relative">
                            <!-- Section Header -->
                            <div class="flex items-center gap-3 mb-6 pb-2 border-b border-gray-200 dark:border-gray-800">
                                <div class="p-2 rounded-lg bg-{{ $groupConfig['color'] }}-100 dark:bg-{{ $groupConfig['color'] }}-900/30 text-{{ $groupConfig['color'] }}-600 dark:text-{{ $groupConfig['color'] }}-400">
                                    <x-dynamic-component :component="$groupConfig['icon']" class="w-6 h-6" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        {{ $groupConfig['label'] }}
                                        <span class="px-2 py-0.5 rounded-full bg-{{ $groupConfig['color'] }}-100 dark:bg-{{ $groupConfig['color'] }}-900/30 text-{{ $groupConfig['color'] }}-700 dark:text-{{ $groupConfig['color'] }}-300 text-xs shadow-sm">
                                            {{ $groupedTasks[$groupKey]->count() }}
                                        </span>
                                    </h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $groupConfig['description'] }}</p>
                                </div>
                            </div>

                            <!-- Tasks Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                @foreach($groupedTasks[$groupKey] as $item)
                                    @php
                                        $client = $item['client'];
                                        $dist = $item['distribution'];
                                        $status = $item['status']; // Should match groupKey
                                        $tag = $dist->tag;
                                        $id = $dist->id;
                                        $idea = $dist->idea;
                                        $hasCustomIdea = !empty($dist->custom_idea);
                                        
                                        $ideaData = [
                                            'name' => $idea?->name ?? 'فكرة مخصصة',
                                            'content' => $idea?->content,
                                            'description' => $idea?->description ?? $dist->custom_idea,
                                            'is_custom' => $hasCustomIdea,
                                        ];

                                        $previewText = $idea?->name ?? $idea?->content ?? $dist->custom_idea ?? 'لا توجد تفاصيل';
                                        
                                        // Simplified Config since we are already grouped
                                        $borderColor = match($status) {
                                            'changes_requested' => 'border-red-500 ring-1 ring-red-500/30',
                                            'pending' => 'border-gray-200 dark:border-gray-800',
                                            'reviewing' => 'border-blue-200 dark:border-blue-800',
                                            'sending' => 'border-indigo-200 dark:border-indigo-800',
                                            'completed' => 'border-green-200 dark:border-green-800',
                                            default => 'border-gray-200',
                                        };
                                    @endphp

                                    <div wire:key="task-{{ $id }}" class="group relative flex flex-col h-full bg-white dark:bg-gray-900 rounded-2xl border transition-all duration-300 {{ $borderColor }} hover:shadow-xl dark:shadow-none dark:bg-gray-900/50">
                                        
                                        <!-- Card Header -->
                                        <div class="p-6 pb-2">
                                            <div class="flex items-center gap-3 mb-4">
                                                <div class="w-12 h-12 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center justify-center text-xl font-bold text-gray-900 dark:text-white border border-gray-100 dark:border-gray-700">
                                                    {{ Str::substr($client->company ?? '?', 0, 1) }}
                                                </div>
                                                <div>
                                                    <h3 class="font-bold text-gray-900 dark:text-white text-lg leading-tight line-clamp-1">
                                                        {{ $client->company ?? 'عميل غير معروف' }}
                                                    </h3>
                                                    <span class="text-[10px] font-mono font-medium text-gray-500">{{ $client->code }}</span>
                                                </div>
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-[#ff6600]/10 text-[#ff6600] border border-[#ff6600]/20">
                                                {{ $tag->name ?? 'تاق' }}
                                            </span>
                                        </div>

                                        <!-- Idea Details -->
                                        <div class="px-6 mb-4">
                                            <button @click="activeIdea = {{ json_encode($ideaData) }}" class="w-full text-right hover:bg-gray-50 dark:hover:bg-gray-800/50 p-3 rounded-xl border border-dashed border-gray-200 dark:border-gray-700 transition-colors">
                                                <label class="block text-xs font-bold text-gray-400 mb-1 cursor-pointer">تفاصيل الفكرة</label>
                                                <p class="text-sm text-gray-700 dark:text-gray-300 font-medium line-clamp-1">{{ Str::limit($previewText, 60) }}</p>
                                            </button>
                                        </div>

                                        <!-- Dynamic Content Based on Status -->
                                        <div class="flex-1 px-6">
                                            <!-- Feedback -->
                                            @if($status === 'changes_requested' && $dist->reviewer_feedback)
                                                <div class="mb-4 bg-red-50 dark:bg-red-900/20 p-4 rounded-xl border border-red-100 dark:border-red-800">
                                                    <label class="block text-xs font-bold text-red-600 dark:text-red-400 mb-1 flex items-center gap-1">
                                                        <x-heroicon-m-exclamation-circle class="w-4 h-4" />
                                                        ملاحظات التعديل:
                                                    </label>
                                                    <p class="text-sm text-red-800 dark:text-red-300 leading-relaxed">{{ $dist->reviewer_feedback }}</p>
                                                </div>
                                            @endif

                                            <!-- Attachment -->
                                            @if($dist->attachment_path)
                                                <div class="mb-4 relative group/image overflow-hidden rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-black/50 aspect-video flex items-center justify-center">
                                                    <img src="{{ Storage::url($dist->attachment_path) }}" alt="Submission" class="w-full h-full object-cover" />
                                                    <a href="{{ Storage::url($dist->attachment_path) }}" target="_blank" class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover/image:opacity-100 transition-opacity">
                                                        <x-heroicon-o-eye class="w-8 h-8 text-white" />
                                                    </a>
                                                </div>
                                                 @if($dist->designer_notes)
                                                    <p class="text-xs text-gray-500 mb-4 bg-gray-50 dark:bg-gray-800 p-2 rounded block">
                                                        <span class="font-bold">ملاحظاتك:</span> {{ $dist->designer_notes }}
                                                    </p>
                                                @endif
                                            @endif
                                        </div>

                                        <!-- Actions Footer -->
                                        <div class="mt-auto px-6 py-4 bg-gray-50/50 dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 rounded-b-2xl">
                                            
                                            {{-- 1) Pending --}}
                                            @if($status === 'pending')
                                                <button wire:click="mountAction('submitTask', { distribution_id: {{ $id }} })" 
                                                        class="w-full flex items-center justify-center gap-2 bg-[#441188] hover:bg-[#350d6b] text-white py-3 rounded-xl text-sm font-bold shadow-lg shadow-[#441188]/20 transition-all transform active:scale-[0.98]">
                                                    <x-heroicon-m-paper-airplane class="w-5 h-5" />
                                                    <span>إتمام وإرسال</span>
                                                </button>

                                            {{-- 2) Reviewing --}}
                                            @elseif($status === 'reviewing')
                                                <button wire:click="mountAction('editTask', { distribution_id: {{ $id }} })" 
                                                        class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 py-3 rounded-xl text-sm font-bold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                                    <x-heroicon-m-pencil-square class="w-5 h-5" />
                                                    <span>تعديل التسليم</span>
                                                </button>

                                            {{-- 3) Sending --}}
                                            @elseif($status === 'sending')
                                                <div class="w-full py-3 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-300 rounded-xl flex items-center justify-center gap-2 font-bold text-sm cursor-not-allowed border border-indigo-100 dark:border-indigo-800">
                                                    <x-heroicon-m-rocket-launch class="w-5 h-5 animate-pulse" />
                                                    <span>قيد الإرسال للعميل</span>
                                                </div>

                                            {{-- 4) Changes Requested --}}
                                            @elseif($status === 'changes_requested')
                                                <button wire:click="mountAction('submitTask', { distribution_id: {{ $id }} })" 
                                                        class="w-full flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white py-3 rounded-xl text-sm font-bold shadow-lg shadow-red-600/20 transition-all transform active:scale-[0.98]">
                                                    <x-heroicon-m-arrow-path class="w-5 h-5" />
                                                    <span>إرسال التعديلات</span>
                                                </button>

                                            {{-- 5) Completed --}}
                                            @elseif($status === 'completed')
                                                <div class="w-full py-3 bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 rounded-xl flex items-center justify-center gap-2 font-bold text-sm border border-green-100 dark:border-green-800">
                                                    <x-heroicon-m-check-badge class="w-5 h-5" />
                                                    <span>مكتملة ومعتمدة</span>
                                                </div>
                                            @endif
                                            
                                        </div>

                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        @endif

        <!-- Idea Modal (Kept Excatly As Is) -->
        <div x-show="activeIdea" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center px-4 sm:px-6"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" @click="activeIdea = null"></div>
            <div class="relative bg-white dark:bg-gray-900 rounded-2xl shadow-xl max-w-lg w-full overflow-hidden transform transition-all border border-gray-100 dark:border-gray-800">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/50">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-m-light-bulb class="w-5 h-5 text-[#441188]" />
                        <span x-text="activeIdea?.name"></span>
                    </h3>
                    <button @click="activeIdea = null" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <x-heroicon-m-x-mark class="w-6 h-6" />
                    </button>
                </div>
                <div class="px-6 py-6 max-h-[70vh] overflow-y-auto">
                    <template x-if="activeIdea?.is_custom">
                        <div class="mb-4 p-3 bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 text-sm rounded-lg border border-orange-100 dark:border-orange-900/30 flex items-center gap-2">
                            <x-heroicon-m-information-circle class="w-5 h-5" />
                            هذه فكرة مخصصة للعميل.
                        </div>
                    </template>
                    <div class="mb-6">
                        <label class="block text-xs font-bold text-gray-400 mb-2 uppercase">المحتوى المقترح</label>
                        <div class="text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[#111116] p-4 rounded-xl border border-gray-100 dark:border-gray-800 text-sm leading-relaxed whitespace-pre-wrap" x-text="activeIdea?.content || 'لا يوجد محتوى نصي.'"></div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-2 uppercase">الوصف / التوجيهات</label>
                        <div class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed whitespace-pre-wrap" x-text="activeIdea?.description || 'لا توجد توجيهات.'"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
