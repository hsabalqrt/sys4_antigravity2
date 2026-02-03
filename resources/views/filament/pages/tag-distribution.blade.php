<x-filament-panels::page>
    @if($assignments->isEmpty())
        <div class="flex flex-col items-center justify-center p-6 bg-white rounded-lg shadow dark:bg-gray-800">
            <x-heroicon-o-inbox class="w-10 h-10 text-gray-400" />
            <p class="mt-2 text-lg font-medium text-gray-500">لا يوجد توزيعات لهذا الأسبوع</p>
        </div>
    @else
        <div class="mb-6 flex items-center justify-center">
            <div class="px-6 py-2 bg-white dark:bg-gray-800 rounded-full shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
                <span class="text-lg font-medium text-gray-700 dark:text-gray-200">
                    {{ $this->getWeekDateRange() }}
                </span>
            </div>
        </div>

        @php
            $groupedAssignments = $assignments->groupBy('designer.user.name');
            $weekStart = \Carbon\Carbon::parse($selectedWeek);
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->copy()->addDays($i);
                if ($day->dayOfWeek !== \Carbon\Carbon::FRIDAY) {
                    $days[] = $day;
                }
            }
        @endphp

        <div x-data="{ activeTab: '{{ $groupedAssignments->keys()->first() }}' }" class="space-y-6">
            <div class="border-b border-gray-200 dark:border-white/10">
                <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Tabs">
                    @foreach($groupedAssignments as $designerName => $designerAssignments)
                        <button
                            type="button"
                            @click="activeTab = '{{ $designerName }}'"
                            :class="activeTab === '{{ $designerName }}'
                                ? 'border-primary-500 font-bold text-primary dark:text-white dark:border-primary-500'
                                : 'border-transparent font-bold text-gray-900 hover:border-gray-300 hover:text-primary dark:text-gray-500 dark:hover:text-white dark:hover:border-gray-300'"
                            class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        >
                            {{ $designerName }}
                        </button>
                    @endforeach
                </nav>
            </div>

            @foreach($groupedAssignments as $designerName => $designerAssignments)
                <div x-show="activeTab === '{{ $designerName }}'" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <div class="px-6 py-4 flex border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                        <h3 class="text-lg font-bold text-gray-950 dark:text-white flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-user" style="color: #ff6600;" class="w-5 h-5" />
                            {{ $designerName }}
                        </h3>
                        <div class="flex items-center gap-2">
                             @php
                                $firstAssignment = $designerAssignments->first();
                                $designerId = $firstAssignment ? $firstAssignment->designer_id : null;
                            @endphp
                            @if($designerId)
                                <x-filament::button
                                    size="xs"
                                    style="margin-right: 20px;"
                                    color="danger"
                                    wire:click="distributeVeryHighTagsForDesigner({{ $designerId }})"
                                    wire:confirm="هل أنت متأكد من توزيع التاقات ذات الأهمية العالية جداً لهذا المصمم؟"
                                >
                                    توزيع العالية جداً
                                </x-filament::button>

                                <x-filament::button
                                    size="xs"
                                    color="warning"
                                    wire:click="distributeHighTagsForDesigner({{ $designerId }})"
                                    wire:confirm="هل أنت متأكد من توزيع التاقات العالية (High) لهذا المصمم؟"
                                    :disabled="!$this->hasDistributedVeryHigh($designerId)"
                                >
                                    توزيع العالية
                                </x-filament::button>

                                <x-filament::button
                                    size="xs"
                                    color="success"
                                    wire:click="distributeMediumLowTagsForDesigner({{ $designerId }})"
                                    wire:confirm="هل أنت متأكد من توزيع التاقات المتوسطة والمنخفضة لهذا المصمم؟"
                                    :disabled="!$this->hasDistributedHigh($designerId)"
                                >
                                    توزيع البقية
                                </x-filament::button>

                                <x-filament::button
                                    size="xs"
                                    color="info"
                                    icon="heroicon-o-sparkles"
                                    wire:click="distributeIdeasForDesigner({{ $designerId }})"
                                    wire:confirm="هل أنت متأكد من توزيع الأفكار لهذا المصمم؟"
                                >
                                    توزيع الأفكار
                                </x-filament::button>

                                <div class="w-px h-6 bg-gray-300 dark:bg-gray-500 mx-2"></div>

                                <x-filament::button
                                    size="xs"
                                    color="danger"
                                    icon="heroicon-o-trash"
                                    outlined
                                    wire:click="clearTagsForDesigner({{ $designerId }})"
                                    wire:confirm="هل أنت متأكد من حذف جميع تاقات هذا المصمم؟"
                                >
                                    حذف التاقات
                                </x-filament::button>

                                <x-filament::button
                                    size="xs"
                                    color="warning"
                                    icon="heroicon-o-light-bulb"
                                    outlined
                                    wire:click="clearIdeasForDesigner({{ $designerId }})"
                                    wire:confirm="هل أنت متأكد من حذف جميع أفكار هذا المصمم؟"
                                >
                                    حذف الأفكار
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-right">
                            <thead class="bg-gray-50 dark:bg-white/5 text-gray-700 dark:text-white font-medium">
                                <tr>
                                    <th class="px-4 py-3 min-w-[150px]">العميل</th>
                                    @foreach($days as $day)
                                        <th class="px-4 py-3 min-w-[120px] text-center">
                                            <div class="font-bold">{{ $day->translatedFormat('l') }}</div>
                                            <div class="text-[10px] font-normal text-gray-500">{{ $day->format('Y-m-d') }}</div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach($designerAssignments as $assignment)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex flex-col">
                                                    <span>{{ $assignment->client->company ?? $assignment->client->client_name }}</span>
                                                    <span class="text-xs text-gray-500 font-normal">{{ $assignment->client->category->name ?? 'بدون تصنيف' }}</span>
                                                </div>

                                                
                                            </div>
                                        </td>
                                        
                                        @php
                                            $distributions = \App\Models\ClientTagDistribution::with('tag')
                                                ->where('client_designer_id', $assignment->id)
                                                ->get();
                                        @endphp
                                        

                                        @foreach($days as $day)
                                            @php
                                                $dayDistributions = $distributions->where('distribution_date', $day->format('Y-m-d'));
                                            @endphp
                                            <td class="px-4 py-3 text-center align-top">
                                                <div class="flex flex-col gap-1 items-center">
                                                    @forelse($dayDistributions as $dist)
                                                        <div class="group flex items-center gap-1 w-full">
                                                            <span class="flex flex-col items-center px-2 py-1 rounded text-xs font-medium bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400 ring-1 ring-inset ring-primary-700/10 dark:ring-primary-400/20 flex-1 justify-center min-w-[80px]">
                                                                {{ $dist->tag->name }}
                                                                @if($dist->custom_idea)
                                                                    <span class="text-[10px] border-t border-primary-500/20 pt-0.5 mt-0.5 block w-[70px] max-w-[70px] overflow-hidden text-center truncate px-1 text-teal-600 dark:text-teal-400" title="{{ $dist->custom_idea }}" style="max-width: 70px !important;">
                                                                        {{ $dist->custom_idea }}
                                                                    </span>
                                                                @endif

                                                                @if($dist->idea)
                                                                    <span class="text-[10px] border-t border-primary-500/20 pt-0.5 mt-0.5 block w-full max-w-[70px] text-center truncate px-1" style="color: #ff6011aa !important;">
                                                                        {{ $dist->idea->name }}
                                                                    </span>
                                                                @endif

                                                                @if($dist->scheduled_sending_at)
                                                                    <span class="text-[8px] text-gray-400 block w-full text-center border-t border-gray-100 dark:border-white/5 mt-0.5 pt-0.5" dir="ltr">
                                                                        {{ $dist->scheduled_sending_at->format('Y-m-d H:i') }}
                                                                    </span>
                                                                @endif

                                                                {{-- @if($dist->tag && !empty($dist->tag->weekly_day))
                                                                    <span class="text-[8px] text-gray-500 dark:text-gray-400 block w-full text-center mt-0.5 truncate border-t border-gray-100 dark:border-white/5 pt-0.5" title="{{ implode(', ', $dist->tag->weekly_day) }}">
                                                                       {{ implode(', ', $dist->tag->weekly_day) }}
                                                                    </span>
                                                                @endif --}} 
                                                            </span>
                                                            <button 
                                                                wire:click="openMoveModal({{ $dist->id }})"
                                                                class="text-gray-300 hover:text-primary-600 transition-colors"
                                                                title="نقل إلى يوم آخر"
                                                            >
                                                                <x-filament::icon icon="heroicon-m-arrows-right-left" class="w-4 h-4" />
                                                            </button>
                                                            <button 
                                                                wire:click="openEditModal({{ $dist->id }})"
                                                                class="text-gray-300 hover:text-primary-600 transition-colors"
                                                                title="تعديل التاق"
                                                            >
                                                                <x-filament::icon icon="heroicon-m-pencil-square" class="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    @empty
                                                        <span class="text-gray-300">-</span>
                                                    @endforelse
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <x-filament::modal id="move-tag-modal" width="sm">
            <x-slot name="heading">
                نقل التاق إلى يوم آخر
            </x-slot>

            <div class="py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">اختر المصمم</label>
                    <select wire:model="newDesignerId" wire:key="designer-select-{{ $editingDistributionId }}" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white sm:text-sm">
                        @foreach($designers as $id => $name)
                            <option value="{{ $id }}" wire:key="designer-option-{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">اختر اليوم الجديد</label>
                <select wire:model="newDate" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white sm:text-sm">
                    @foreach($this->getWeekDays() as $dayDate)
                        @php $day = \Carbon\Carbon::parse($dayDate); @endphp
                        <option value="{{ $dayDate }}">
                            {{ $day->translatedFormat('l') }} ({{ $day->format('Y-m-d') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-filament::button wire:click="moveTag">
                        حفظ
                    </x-filament::button>
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'move-tag-modal' })">
                        إلغاء
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        <x-filament::modal id="edit-tag-modal" width="sm">
            <x-slot name="heading">
                تعديل التاق
            </x-slot>

            <div class="py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">التاق</label>
                    <select wire:model.live="newTagId" wire:key="tag-select-{{ $editingDistributionId }}" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white sm:text-sm">
                        @foreach($availableTags as $id => $name)
                            <option value="{{ $id }}" wire:key="tag-option-{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">الفكرة (اختياري)</label>
                    <select wire:model.live="newIdeaId" wire:key="idea-select-{{ $editingDistributionId }}" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white sm:text-sm">
                        <option value="">بدون فكرة</option>
                        @foreach($availableIdeas as $id => $name)
                            <option value="{{ $id }}" wire:key="idea-option-{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">نص مخصص (اختياري)</label>
                    <input type="text" wire:model="newCustomIdea" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white sm:text-sm" placeholder="اكتب فكرة مخصصة...">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">التاريخ</label>
                    <select wire:model="newDate" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white sm:text-sm">
                        @foreach($this->getWeekDays() as $dayDate)
                            @php $day = \Carbon\Carbon::parse($dayDate); @endphp
                            <option value="{{ $dayDate }}">
                                {{ $day->translatedFormat('l') }} ({{ $day->format('Y-m-d') }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-filament::button wire:click="updateTag">
                        حفظ
                    </x-filament::button>
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'edit-tag-modal' })">
                        إلغاء
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    @endif
</x-filament-panels::page>
