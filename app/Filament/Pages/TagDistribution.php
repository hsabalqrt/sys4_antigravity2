<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\ClientDesigner;
use App\Models\Tag;
use App\Models\ClientTagDistribution;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Collection;
use App\Models\Idea;
use App\Models\Designer;
use Illuminate\Support\Facades\Log;

class TagDistribution extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static string $view = 'filament.pages.tag-distribution';

    protected static ?string $title = 'توزيع التاقات';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $selectedWeek = null;

    public $assignments = [];
    
    public $editingDistributionId = null;
    public $newDate = null;
    public $newDesignerId = null;
    public $designers = [];
    public $newTagId = null;
    public $newIdeaId = null;
    public $newCustomIdea = null;
    public $availableTags = [];
    public $availableIdeas = [];
    public $distributionStatus = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_tag_distribution');
    }


    public function openMoveModal($id): void
    {
        $this->editingDistributionId = $id;
        $distribution = ClientTagDistribution::find($id);
        $this->newDate = $distribution?->distribution_date;
        
        // Load designers for the dropdown
        $this->designers = Designer::with('user')->get()->pluck('user.name', 'id')->toArray();
        $this->newDesignerId = $distribution?->clientDesigner?->designer_id;

        $this->dispatch('open-modal', id: 'move-tag-modal');
    }

    public function openEditModal($id): void
    {
        $this->editingDistributionId = $id;
        $distribution = ClientTagDistribution::find($id);
        
        if (!$distribution) return;

        $this->newDate = $distribution->distribution_date;
        $this->newTagId = (int) $distribution->tag_id;
        $this->newIdeaId = $distribution->idea_id;
        $this->newCustomIdea = $distribution->custom_idea;

        // Load available tags for this assignment
        $assignment = ClientDesigner::find($distribution->client_designer_id);
        $this->availableTags = [];

        if ($assignment) {
            if ($assignment->subscription && !$assignment->subscription->is_main) {
                $this->availableTags = $assignment->subscription->tags->pluck('name', 'id')->toArray();
            } elseif ($assignment->client && $assignment->client->category) {
                $this->availableTags = $assignment->client->category->tags->pluck('name', 'id')->toArray();
            }
        }

        // Fallback: If list is empty, load all tags to ensure editability
        if (empty($this->availableTags)) {
            $this->availableTags = Tag::pluck('name', 'id')->toArray();
        }

        // Ensure current tag is in the list
        if (!array_key_exists($this->newTagId, $this->availableTags)) {
            $tag = Tag::find($this->newTagId);
            if ($tag) {
                $this->availableTags[$tag->id] = $tag->name;
            }
        }

        $this->loadAvailableIdeas($distribution->client_designer->client_id ?? null);

        $this->dispatch('open-modal', id: 'edit-tag-modal');
    }

    public function updatedNewTagId()
    {
        $distribution = ClientTagDistribution::find($this->editingDistributionId);
        $clientId = $distribution?->client_designer?->client_id;
        $this->loadAvailableIdeas($clientId);
        $this->newIdeaId = null; // Reset idea when tag changes
    }

    protected function loadAvailableIdeas($clientId)
    {
        if (!$this->newTagId) {
            $this->availableIdeas = [];
            return;
        }

        $query = Idea::whereHas('tags', function ($q) {
            $q->where('tags.id', $this->newTagId);
        });

        if ($clientId) {
            $query->whereDoesntHave('blockedClients', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });

            // Exclude ideas already used by this client in the current week
            $usedIdeaIds = ClientTagDistribution::whereHas('clientDesigner', function($q) use ($clientId) {
                $q->where('client_id', $clientId)
                  ->where('week_start_date', $this->selectedWeek);
            })->whereNotNull('idea_id')->pluck('idea_id')->toArray();

            // Allow the current idea (if set) to remain available so it can be selected/kept
            if ($this->newIdeaId && in_array($this->newIdeaId, $usedIdeaIds)) {
                 $usedIdeaIds = array_diff($usedIdeaIds, [$this->newIdeaId]);
            }

            if (!empty($usedIdeaIds)) {
                $query->whereNotIn('id', $usedIdeaIds);
            }
        }

        $this->availableIdeas = $query->pluck('name', 'id')->toArray();
    }

    public function moveTag(): void
    {
        $this->validate([
            'newDate' => 'required|date',
            'newDesignerId' => 'required|exists:designers,id',
        ]);

        $distribution = ClientTagDistribution::find($this->editingDistributionId);
        
        if ($distribution) {
            $currentClientDesigner = $distribution->clientDesigner;
            
            // If designer changed, we need to move the tag to the new designer's assignment
            if ($currentClientDesigner->designer_id != $this->newDesignerId) {
                // Find or create assignment for the new designer for this week and client
                $newAssignment = ClientDesigner::firstOrCreate(
                    [
                        'client_id' => $currentClientDesigner->client_id,
                        'designer_id' => $this->newDesignerId,
                        'week_start_date' => $currentClientDesigner->week_start_date,
                    ],
                    [
                        'subscription_id' => null,
                    ]
                );

                $distribution->client_designer_id = $newAssignment->id;
            }

            $distribution->distribution_date = $this->newDate;
            $distribution->scheduled_sending_at = $this->calculateScheduledSendingAt($this->newDate, $distribution->tag_id, $distribution->idea_id, $this->selectedWeek);
            $distribution->save();

            Notification::make()
                ->title('تم نقل التاق بنجاح')
                ->success()
                ->send();
        }

        $this->reset(['editingDistributionId', 'newDate', 'newDesignerId', 'designers']);
        $this->dispatch('close-modal', id: 'move-tag-modal');
        $this->loadAssignments();
    }

    public function updateTag(): void
    {
        $this->validate([
            'newDate' => 'required|date',
            'newTagId' => 'required|exists:tags,id',
        ]);

        $distribution = ClientTagDistribution::find($this->editingDistributionId);
        
        if ($distribution) {
            $distribution->update([
                'distribution_date' => $this->newDate,
                'tag_id' => $this->newTagId,
                'idea_id' => $this->newIdeaId,
                'custom_idea' => $this->newCustomIdea,
                'scheduled_sending_at' => $this->calculateScheduledSendingAt($this->newDate, $this->newTagId, $this->newIdeaId, $this->selectedWeek),
            ]);

            Notification::make()
                ->title('تم تعديل التاق بنجاح')
                ->success()
                ->send();
        }

        $this->reset(['editingDistributionId', 'newDate', 'newTagId', 'newIdeaId', 'newCustomIdea', 'availableTags', 'availableIdeas']);
        $this->dispatch('close-modal', id: 'edit-tag-modal');
        $this->loadAssignments();
    }

    public function getWeekDays(): array
    {
        $weekStart = Carbon::parse($this->selectedWeek);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            if ($day->dayOfWeek !== Carbon::FRIDAY) {
                $days[] = $day->format('Y-m-d');
            }
        }
        return $days;
    }

    public function getWeekDateRange(): string
    {
        $start = Carbon::parse($this->selectedWeek);
        $end = $start->copy()->addDays(6);
        
        return sprintf(
            'الأسبوع من %s إلى %s',
            $start->translatedFormat('j F Y'),
            $end->translatedFormat('j F Y')
        );
    }

    public function mount(): void
    {
        $this->selectedWeek = request()->query('week', Carbon::now()->startOfWeek()->format('Y-m-d'));
        $this->loadAssignments();
    }

    public function loadAssignments(): void
    {
        $this->assignments = ClientDesigner::with(['client.category.tags', 'designer.user', 'client.category', 'subscription.tags', 'distributions.idea', 'client.clientNeeds.tags', 'client.tags'])
            ->where('week_start_date', $this->selectedWeek)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Helper to collect tags based on Hybrid Logic (Category + Location + Needs + Manual)
     */
    protected function collectTagsForAssignment($assignment)
    {
        // 1. Tags from Client Category (Auto)
        $categoryTags = collect();
        if ($assignment->client->category) {
             $categoryTags = $assignment->client->category->tags
                ->filter(function ($tag) {
                    return $tag->is_active && $tag->is_auto_assigned;
                })
                ->filter(function ($tag) use ($assignment) {
                    // Safety Check: If tag is restricted to specific LOCATIONS, client must match
                    if ($tag->locations()->exists()) {
                        return $tag->locations()->where('locations.id', $assignment->client->location_id)->exists();
                    }
                    return true;
                });
        }

        // 2. Tags from Client Location (Auto)
        $locationTags = collect();
        if ($assignment->client->location) {
            $locationTags = $assignment->client->location->tags
                ->filter(function ($tag) {
                    return $tag->is_active && $tag->is_auto_assigned;
                })
                ->filter(function ($tag) use ($assignment) {
                    // Safety Check: If tag is restricted to specific CATEGORIES, client must match
                    if ($tag->categories()->exists()) {
                         return $tag->categories()->where('categories.id', $assignment->client->category_id)->exists();
                    }
                    return true;
                });
        }

        // 3. Tags from Client Needs (Auto)
        $clientNeedsTags = $assignment->client->clientNeeds->pluck('tags')->flatten()
            ->unique('id')
            ->filter(function ($tag) {
                return $tag->is_active && $tag->is_auto_assigned;
            })
            ->filter(function ($tag) use ($assignment) {
                // Safety Check: If tag is restricted to specific LOCATIONS, client must match
                if ($tag->locations()->exists()) {
                    return $tag->locations()->where('locations.id', $assignment->client->location_id)->exists();
                }
                return true;
            });

        // 4. Manual Tags (Manual Override - No Location/Category checks typically, as it's manual)
        $manualTags = $assignment->client->tags
            ->filter(function ($tag) {
                return $tag->is_active;
            });

         // Re-enabled debug logs for verification
        Log::info('Debug Hybrid Logic (LocationSafe): Client ' . $assignment->client->company, [
            'category_tags' => $categoryTags->pluck('name')->toArray(),
            'location_tags' => $locationTags->pluck('name')->toArray(),
            'needs_tags' => $clientNeedsTags->pluck('name')->toArray(),
            'manual_tags' => $manualTags->pluck('name')->toArray(),
        ]);

        // 5. Merge all unique tags
        $tags = $categoryTags
            ->merge($locationTags)
            ->merge($clientNeedsTags)
            ->merge($manualTags)
            ->unique('id');

        // EXCLUSION: Remove tags assigned to Secondary Subscriptions
        $secondaryTagsIds = $assignment->client->subscriptions()
            ->where('is_main', false)
            ->with('tags')
            ->get()
            ->pluck('tags')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->toArray();
            
        return $tags->whereNotIn('id', $secondaryTagsIds);
    }


    public function autoDistribute(): void
    {
        $weekStart = Carbon::parse($this->selectedWeek);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            if ($day->dayOfWeek !== Carbon::FRIDAY) {
                $days[] = $day->format('Y-m-d');
            }
        }

        $count = 0;

        // Group assignments by designer to balance their load
        $assignmentsByDesigner = $this->assignments->groupBy('designer_id');

        foreach ($assignmentsByDesigner as $designerId => $designerAssignments) {
            // Track daily load for this designer: date => count
            $dailyLoad = array_fill_keys($days, 0);
            
            // Track days used by each assignment: assignment_id => [date => [importance_rank, ...]]
            $assignmentUsage = [];

            // Collect all flexible tags that need distribution
            $pendingFlexibleTags = [];

            foreach ($designerAssignments as $assignment) {
                // Clear existing distributions for this assignment
                ClientTagDistribution::where('client_designer_id', $assignment->id)->delete();

                // Determine source of tags
                if ($assignment->subscription && !$assignment->subscription->is_main) {
                    $tags = $assignment->subscription->tags ?? collect();
                    $tags = $tags->where('is_active', true);
                } else {
                    // Main Subscription Source: Hybrid Model
                    // 1. Tags from Client Category (Auto)
                    $categoryTags = collect();
                    if ($assignment->client->category) {
                         $categoryTags = $assignment->client->category->tags
                            ->filter(function ($tag) {
                                return $tag->is_active && $tag->is_auto_assigned;
                            });
                    }

                    // 2. Tags from Client Location (Auto)
                    $locationTags = collect();
                    if ($assignment->client->location) {
                        $locationTags = $assignment->client->location->tags
                            ->filter(function ($tag) {
                                return $tag->is_active && $tag->is_auto_assigned;
                            });
                    }

                    // 3. Tags from Client Needs (Auto)
                    $clientNeedsTags = $assignment->client->clientNeeds->pluck('tags')->flatten()
                        ->unique('id')
                        ->filter(function ($tag) {
                            return $tag->is_active && $tag->is_auto_assigned;
                        });

                    // 4. Manual Tags (Manual Override)
                    $manualTags = $assignment->client->tags
                        ->filter(function ($tag) {
                            return $tag->is_active;
                        });

                    Log::info('AutoDistribute Debug: Client ' . $assignment->client->company, [
                        'category_id' => $assignment->client->category_id,
                        'category_tags_count' => $categoryTags->count(),
                        'location_tags_count' => $locationTags->count(),
                        'needs_tags_count' => $clientNeedsTags->count(),
                        'manual_tags_count' => $manualTags->count(),
                    ]);

                    // 5. Merge all unique tags
                    $tags = $categoryTags
                        ->merge($locationTags)
                        ->merge($clientNeedsTags)
                        ->merge($manualTags)
                        ->unique('id');

                    // EXCLUSION: Remove tags assigned to Secondary Subscriptions
                    $secondaryTagsIds = $assignment->client->subscriptions()
                        ->where('is_main', false)
                        ->with('tags')
                        ->get()
                        ->pluck('tags')
                        ->flatten()
                        ->pluck('id')
                        ->unique()
                        ->toArray();
                        
                    $tags = $tags->whereNotIn('id', $secondaryTagsIds);
                }

                if ($tags->isEmpty()) {
                    Log::info('AutoDistribute Debug: No tags found for client ' . $assignment->client->company);
                    continue;
                }

                if ($tags->isEmpty()) {
                    continue;
                }

                // Filter expired tags
                $tags = $tags->filter(function ($tag) use ($weekStart) {
                    if ($tag->is_there_date_for_sending && $tag->date_for_sending_yearly) {
                        try {
                            $tagDate = Carbon::parse($tag->date_for_sending_yearly);
                            // If the tag date is strictly in the past relative to the selected week, exclude it
                            if ($tagDate->lt($weekStart->startOfDay())) {
                                return false;
                            }
                        } catch (\Exception $e) {
                            return false;
                        }
                    }
                    return true;
                });

                if ($tags->isEmpty()) {
                    continue;
                }

                // Randomize tags initially
                $tagsList = $tags->shuffle()->values();
                $tagsCount = $tagsList->count();

                // Get designs count
                $designsCount = $assignment->subscription->designs_count ?? 0;
                if ($designsCount <= 0) continue;

                $distributedCount = 0;
                $tagIndex = 0;
                $hasDistributedVeryHigh = false;
                $isSecondary = $assignment->subscription && !$assignment->subscription->is_main;

                while ($distributedCount < $designsCount) {
                    // Safety check
                    if ($tagIndex > max($tagsCount, $designsCount) * 10) break;

                    $tag = $tagsList->get($tagIndex % $tagsCount);
                    $importance = trim(strtolower($tag->importance ?? ''));
                    
                    $veryHighValues = [
                        'very_high', 'very high', 'veryhigh',
                        'عالية جدا', 'عالية جداً', 'عالي جدا', 'عالي جداً',
                        'هام جدا', 'هام جداً'
                    ];
                    $highValues = ['high', 'عالية', 'عالي', 'هام'];
                    $mediumValues = ['medium', 'متوسطة', 'متوسط'];
                    $lowValues = ['low', 'منخفضة', 'منخفض'];

                    $isVeryHigh = in_array($importance, $veryHighValues);
                    
                    // Determine Rank
                    $rank = 5;
                    if ($isVeryHigh) $rank = 1;
                    elseif (in_array($importance, $highValues)) $rank = 2;
                    elseif (in_array($importance, $mediumValues)) $rank = 3;
                    elseif (in_array($importance, $lowValues)) $rank = 4;

                    // Constraints
                    if (!$isSecondary && $isVeryHigh && $hasDistributedVeryHigh) {
                        $tagIndex++;
                        continue;
                    }

                    if (!$isSecondary && $tagIndex >= $tagsCount) {
                        $repeatableValues = array_merge($mediumValues, $lowValues);
                        if (!in_array($importance, $repeatableValues)) {
                            $tagIndex++;
                            continue;
                        }
                    }

                    $tagIndex++;

                    // Check for fixed date requirements
                    $targetDate = null;
                    if ($tag->is_there_date_for_sending) {
                        foreach ($days as $day) {
                            $carbonDay = Carbon::parse($day);
                            
                            // Check Weekly Day
                            if ($tag->weekly_day) {
                                $weeklyDay = trim($tag->weekly_day);
                                if (
                                    strcasecmp($carbonDay->format('l'), $weeklyDay) === 0 ||
                                    $carbonDay->locale('ar')->translatedFormat('l') === $weeklyDay
                                ) {
                                    $targetDate = $day;
                                    break;
                                }
                            }

                            // Check Yearly Date
                            if ($tag->date_for_sending_yearly) {
                                try {
                                    $tagDate = Carbon::parse($tag->date_for_sending_yearly);
                                    if ($carbonDay->month === $tagDate->month && $carbonDay->day === $tagDate->day) {
                                        $targetDate = $day;
                                        break;
                                    }
                                } catch (\Exception $e) {}
                            }
                        }
                    }

                    if ($targetDate) {
                        // Fixed date assignment
                        ClientTagDistribution::create([
                            'client_designer_id' => $assignment->id,
                            'tag_id' => $tag->id,
                            'distribution_date' => $targetDate,
                            'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tag->id, null, $this->selectedWeek),
                        ]);
                        $dailyLoad[$targetDate]++;
                        $assignmentUsage[$assignment->id][$targetDate][] = $rank;
                        $count++;
                        $distributedCount++;
                        if ($isVeryHigh) $hasDistributedVeryHigh = true;
                    } else {
                        // Flexible assignment - add to pending list
                        $pendingFlexibleTags[] = [
                            'assignment_id' => $assignment->id,
                            'tag_id' => $tag->id,
                            'rank' => $rank,
                            'designs_count' => $designsCount,
                        ];
                        $distributedCount++; // Count it as "to be distributed"
                        if ($isVeryHigh) $hasDistributedVeryHigh = true;
                    }
                }
            }

            // Sort pending tags by rank (Very High first)
            usort($pendingFlexibleTags, fn($a, $b) => $a['rank'] <=> $b['rank']);

            foreach ($pendingFlexibleTags as $pendingTag) {
                $assignmentId = $pendingTag['assignment_id'];
                $designsCount = $pendingTag['designs_count'];
                $currentRank = $pendingTag['rank'];
                
                $targetDate = null;
                $clientUsage = $assignmentUsage[$assignmentId] ?? [];

                // 1. Try to find an empty day for this client
                $emptyDays = array_diff($days, array_keys($clientUsage));
                
                if (!empty($emptyDays)) {
                    // Pick empty day with min designer load
                    $minLoad = PHP_INT_MAX;
                    $bestDays = [];
                    foreach ($emptyDays as $day) {
                        $load = $dailyLoad[$day];
                        if ($load < $minLoad) {
                            $minLoad = $load;
                            $bestDays = [$day];
                        } elseif ($load === $minLoad) {
                            $bestDays[] = $day;
                        }
                    }
                    $targetDate = $bestDays[array_rand($bestDays)];
                } else {
                    // 2. No empty days (must merge). 
                    // Filter days that are "mergeable" (e.g. not already full if we had a max limit, but here we assume we can add)
                    // We want to apply the "Very High + Medium/Low" rule.
                    
                    $candidateDays = $days; // All days are candidates since we have to merge
                    
                    // Filter candidates based on preference
                    $preferredDays = [];
                    $acceptableDays = [];

                    foreach ($candidateDays as $day) {
                        $existingRanks = $clientUsage[$day] ?? [];
                        
                        // If already has 2 or more tags, try to avoid adding a 3rd if possible (unless designs > 12?)
                        // But for designs=7, we expect max 2.
                        
                        $hasVeryHigh = in_array(1, $existingRanks);
                        $hasHigh = in_array(2, $existingRanks);
                        
                        // Current is Very High (1)
                        if ($currentRank === 1) {
                            // Prefer pairing with Medium(3) or Low(4)
                            // Avoid pairing with Very High(1) or High(2)
                            if (!$hasVeryHigh && !$hasHigh) {
                                $preferredDays[] = $day;
                            } else {
                                $acceptableDays[] = $day;
                            }
                        }
                        // Current is High (2)
                        elseif ($currentRank === 2) {
                            // Avoid pairing with Very High(1)
                            if (!$hasVeryHigh) {
                                $preferredDays[] = $day;
                            } else {
                                $acceptableDays[] = $day;
                            }
                        }
                        // Current is Medium/Low (3, 4)
                        else {
                            // Prefer pairing with Very High(1) (to satisfy the rule "Merge Very High with Medium/Low")
                            if ($hasVeryHigh) {
                                $preferredDays[] = $day;
                            } else {
                                $acceptableDays[] = $day; // Pairing with High or others is acceptable
                            }
                        }
                    }

                    $finalCandidates = !empty($preferredDays) ? $preferredDays : $acceptableDays;
                    if (empty($finalCandidates)) $finalCandidates = $days; // Fallback

                    // Pick from final candidates based on min load
                    $minLoad = PHP_INT_MAX;
                    $bestDays = [];
                    foreach ($finalCandidates as $day) {
                        $load = $dailyLoad[$day];
                        if ($load < $minLoad) {
                            $minLoad = $load;
                            $bestDays = [$day];
                        } elseif ($load === $minLoad) {
                            $bestDays[] = $day;
                        }
                    }
                    $targetDate = $bestDays[array_rand($bestDays)];
                }

                ClientTagDistribution::create([
                    'client_designer_id' => $assignmentId,
                    'tag_id' => $pendingTag['tag_id'],
                    'distribution_date' => $targetDate,
                    'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $pendingTag['tag_id'], null, $this->selectedWeek),
                ]);

                $dailyLoad[$targetDate]++;
                $assignmentUsage[$assignmentId][$targetDate][] = $currentRank;
                $count++;
            }
        }

        Notification::make()
            ->title('تم توزيع التاقات بنجاح')
            ->body("تم توزيع {$count} تاق على العملاء.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearTags(): void
    {
        $count = 0;
        foreach ($this->assignments as $assignment) {
            $deleted = ClientTagDistribution::where('client_designer_id', $assignment->id)->delete();
            $count += $deleted;
        }

        Notification::make()
            ->title('تم حذف توزيع التاقات')
            ->body("تم حذف {$count} تاق.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearClientTags($assignmentId): void
    {

        ClientTagDistribution::where('client_designer_id', $assignmentId)->delete();

        Notification::make()
            ->title('تم حذف تاقات العميل')
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function autoAssignIdeas(): void
    {
        $assignedCount = 0;
        $weekMaxDate = Carbon::parse($this->selectedWeek)->addDays(6)->format('Y-m-d');
        
        // Group assignments by Client ID to handle duplicate prevention contextually per client
        $assignmentsByClient = $this->assignments->groupBy('client_id');

        foreach ($assignmentsByClient as $clientId => $clientAssignments) {
            
            // Get already used IDs for this client this week
            $usedIdeaIds = ClientTagDistribution::whereHas('clientDesigner', function($q) use ($clientId) {
                $q->where('client_id', $clientId)
                  ->where('week_start_date', $this->selectedWeek);
            })->whereNotNull('idea_id')->pluck('idea_id')->toArray();

            foreach ($clientAssignments as $assignment) {
                // Get all distributions for this assignment, sorted by date DESC
                // This ensures we fill the latest slots first, which is preferred for scheduled ideas (deadlines)
                $distributions = ClientTagDistribution::where('client_designer_id', $assignment->id)
                    ->orderBy('distribution_date', 'desc')
                    ->get();

                foreach ($distributions as $distribution) {
                    // Skip if already has an idea
                    if ($distribution->idea_id) {
                        continue;
                    }

                    $tagId = $distribution->tag_id;

                    // Find valid ideas for this tag and client
                    $validIdeas = Idea::whereHas('tags', function ($query) use ($tagId) {
                            $query->where('tags.id', $tagId);
                        })
                        ->whereDoesntHave('blockedClients', function ($query) use ($clientId) {
                            $query->where('client_id', $clientId);
                        })
                        ->whereNotIn('id', $usedIdeaIds) // Exclude used ideas
                        ->where(function ($query) use ($distribution, $weekMaxDate) {
                            $query->whereNull('scheduled_at')
                                    ->orWhere(function ($sub) use ($distribution, $weekMaxDate) {
                                        $sub->whereDate('scheduled_at', '>=', $distribution->distribution_date)
                                            ->whereDate('scheduled_at', '<=', $weekMaxDate);
                                    });
                        })
                        ->get();
                    
                    if ($validIdeas->isNotEmpty()) {
                        // Priority 1: Scheduled strictly AFTER distribution date (Early delivery)
                        $preferredScheduled = $validIdeas->whereNotNull('scheduled_at')
                            ->filter(fn($i) => Carbon::parse($i->scheduled_at)->gt($distribution->distribution_date))
                            ->sortBy('scheduled_at');

                        // Priority 2: Scheduled ON distribution date (Same day delivery - Last resort)
                        $fallbackScheduled = $validIdeas->whereNotNull('scheduled_at')
                            ->filter(fn($i) => Carbon::parse($i->scheduled_at)->eq($distribution->distribution_date));
                        
                        // Priority 3: Flexible
                        $flexible = $validIdeas->whereNull('scheduled_at');

                        if ($preferredScheduled->isNotEmpty()) {
                            $selectedIdea = $preferredScheduled->first();
                        } elseif ($fallbackScheduled->isNotEmpty()) {
                             $selectedIdea = $fallbackScheduled->first();
                        } else {
                            $selectedIdea = $flexible->random();
                        }
                        
                        $distribution->update([
                            'idea_id' => $selectedIdea->id,
                            'scheduled_sending_at' => $this->calculateScheduledSendingAt($distribution->distribution_date, $distribution->tag_id, $selectedIdea->id, $this->selectedWeek),
                        ]);
                        
                        $usedIdeaIds[] = $selectedIdea->id; // Add to locally tracked used list
                        $assignedCount++;
                    }
                }
            }
        }

        Notification::make()
            ->title('تم توزيع الأفكار بنجاح')
            ->body("تم إسناد {$assignedCount} فكرة.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearIdeas(): void
    {
        $count = 0;
        foreach ($this->assignments as $assignment) {
            $updated = ClientTagDistribution::where('client_designer_id', $assignment->id)
                ->whereNotNull('idea_id')
                ->update(['idea_id' => null]);
            $count += $updated;
        }

        Notification::make()
            ->title('تم حذف الأفكار')
            ->body("تم حذف {$count} فكرة من التوزيع.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearTagsForDesigner($designerId): void
    {
        $assignments = $this->assignments->where('designer_id', $designerId);
        $count = 0;
        
        foreach ($assignments as $assignment) {
            $deleted = ClientTagDistribution::where('client_designer_id', $assignment->id)->delete();
            $count += $deleted;
        }

        // Reset Status
        unset($this->distributionStatus[$designerId]);

        Notification::make()
            ->title('تم حذف تاقات المصمم')
            ->body("تم حذف {$count} تاق.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearIdeasForDesigner($designerId): void
    {
        $assignments = $this->assignments->where('designer_id', $designerId);
        $count = 0;
        
        foreach ($assignments as $assignment) {
            $updated = ClientTagDistribution::where('client_designer_id', $assignment->id)
                ->whereNotNull('idea_id')
                ->update(['idea_id' => null]);
            $count += $updated;
        }

        Notification::make()
            ->title('تم حذف أفكار المصمم')
            ->body("تم حذف {$count} فكرة.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function distributeIdeasForDesigner($designerId): void
    {
        $assignedCount = 0;
        $weekMaxDate = Carbon::parse($this->selectedWeek)->addDays(6)->format('Y-m-d');
        $designerAssignments = $this->assignments->where('designer_id', $designerId);
        
        // Group assignments by Client ID to handle duplicate prevention contextually per client
        $assignmentsByClient = $designerAssignments->groupBy('client_id');

        foreach ($assignmentsByClient as $clientId => $clientAssignments) {
            
            // Get already used IDs for this client this week
            $usedIdeaIds = ClientTagDistribution::whereHas('clientDesigner', function($q) use ($clientId) {
                $q->where('client_id', $clientId)
                  ->where('week_start_date', $this->selectedWeek);
            })->whereNotNull('idea_id')->pluck('idea_id')->toArray();

            foreach ($clientAssignments as $assignment) {
                // Get all distributions for this assignment, sorted by date DESC
                $distributions = ClientTagDistribution::where('client_designer_id', $assignment->id)
                    ->orderBy('distribution_date', 'desc')
                    ->get();

                foreach ($distributions as $distribution) {
                    // Skip if already has an idea
                    if ($distribution->idea_id) {
                        continue;
                    }

                    $tagId = $distribution->tag_id;

                    // Find valid ideas for this tag and client
                    $validIdeas = Idea::whereHas('tags', function ($query) use ($tagId) {
                            $query->where('tags.id', $tagId);
                        })
                        ->whereDoesntHave('blockedClients', function ($query) use ($clientId) {
                            $query->where('client_id', $clientId);
                        })
                        ->whereNotIn('id', $usedIdeaIds) // Exclude used ideas
                        ->where(function ($query) use ($distribution, $weekMaxDate) {
                            $query->whereNull('scheduled_at')
                                    ->orWhere(function ($sub) use ($distribution, $weekMaxDate) {
                                        $sub->whereDate('scheduled_at', '>=', $distribution->distribution_date)
                                            ->whereDate('scheduled_at', '<=', $weekMaxDate);
                                    });
                        })
                        ->get();
                    
                    if ($validIdeas->isNotEmpty()) {
                        // Priority 1: Scheduled strictly AFTER distribution date (Early delivery)
                        $preferredScheduled = $validIdeas->whereNotNull('scheduled_at')
                            ->filter(fn($i) => Carbon::parse($i->scheduled_at)->gt($distribution->distribution_date))
                            ->sortBy('scheduled_at');

                        // Priority 2: Scheduled ON distribution date (Same day delivery - Last resort)
                        $fallbackScheduled = $validIdeas->whereNotNull('scheduled_at')
                            ->filter(fn($i) => Carbon::parse($i->scheduled_at)->eq($distribution->distribution_date));
                        
                        // Priority 3: Flexible
                        $flexible = $validIdeas->whereNull('scheduled_at');

                        if ($preferredScheduled->isNotEmpty()) {
                            $selectedIdea = $preferredScheduled->first();
                        } elseif ($fallbackScheduled->isNotEmpty()) {
                             $selectedIdea = $fallbackScheduled->first();
                        } else {
                            $selectedIdea = $flexible->random();
                        }
                        
                        $distribution->update([
                            'idea_id' => $selectedIdea->id,
                            'scheduled_sending_at' => $this->calculateScheduledSendingAt($distribution->distribution_date, $distribution->tag_id, $selectedIdea->id, $this->selectedWeek),
                        ]);
                        
                        $usedIdeaIds[] = $selectedIdea->id; // Add to locally tracked used list
                        $assignedCount++;
                    }
                }
            }
        }

        Notification::make()
            ->title('تم توزيع أفكار المصمم')
            ->body("تم إسناد {$assignedCount} فكرة.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function distributeVeryHighTagsForDesigner($designerId): void
    {
        $weekStart = Carbon::parse($this->selectedWeek);
        $days = $this->getWeekDays();
        
        $count = 0;
        $designerAssignments = $this->assignments->where('designer_id', $designerId);
        
        // 1. Initialize Context
        $dailyLoad = array_fill_keys($days, 0);
        $assignmentUsage = []; // assignment_id => [date => [rank, ...]]

        // Pre-fill load and usage
        foreach ($designerAssignments as $assignment) {
            foreach ($assignment->distributions as $dist) {
                if (in_array($dist->distribution_date, $days)) {
                    $dailyLoad[$dist->distribution_date]++;
                    
                    $tag = $dist->tag;
                    if ($tag) {
                        $importance = trim(strtolower($tag->importance ?? ''));
                        $veryHighValues = ['very_high', 'very high', 'veryhigh', 'عالية جدا', 'عالية جداً', 'عالي جدا', 'عالي جداً', 'هام جدا', 'هام جداً'];
                        $highValues = ['high', 'عالية', 'عالي', 'هام'];
                        $mediumValues = ['medium', 'متوسطة', 'متوسط'];
                        $lowValues = ['low', 'منخفضة', 'منخفض'];

                        $rank = 5;
                        if (in_array($importance, $veryHighValues)) $rank = 1;
                        elseif (in_array($importance, $highValues)) $rank = 2;
                        elseif (in_array($importance, $mediumValues)) $rank = 3;
                        elseif (in_array($importance, $lowValues)) $rank = 4;

                        $assignmentUsage[$assignment->id][$dist->distribution_date][] = $rank;
                    }
                }
            }
        }

        foreach ($designerAssignments as $assignment) {
            // Check quota
            $designsCount = $assignment->subscription->designs_count ?? 0;
            $currentDistributionsCount = ClientTagDistribution::where('client_designer_id', $assignment->id)->count();
            
            if ($currentDistributionsCount >= $designsCount) {
                continue;
            }

            // Check if Very High already distributed (for Main subscriptions)
            $isSecondary = $assignment->subscription && !$assignment->subscription->is_main;
            $hasVeryHighDistributed = false;

            if (!$isSecondary) {
                // Check in usage logic or DB
                // We built assignmentUsage, we can check ranks
                $usage = $assignmentUsage[$assignment->id] ?? [];
                foreach ($usage as $dateRanks) {
                    if (in_array(1, $dateRanks)) {
                        $hasVeryHighDistributed = true;
                        break;
                    }
                }
                
                if ($hasVeryHighDistributed) {
                    continue; // Skip if main subscription already has a Very High tag
                }
            }

            // Determine source of tags
            if ($assignment->subscription && !$assignment->subscription->is_main) {
                $tags = $assignment->subscription->tags ?? collect();
            } else {
                // Main Subscription Source: Hybrid Model
                $tags = $this->collectTagsForAssignment($assignment);
            }

            if ($tags->isEmpty()) continue;

            // Filter for Very High importance only
            $veryHighTags = $tags->filter(function ($tag) use ($weekStart) {
                $importance = trim(strtolower($tag->importance ?? ''));
                $isVeryHigh = in_array($importance, [
                    'very_high', 'very high', 'veryhigh',
                    'عالية جدا', 'عالية جداً', 'عالي جدا', 'عالي جداً',
                    'هام جدا', 'هام جداً'
                ]);

                if (!$isVeryHigh) return false;

                // Check Expiration
                if ($tag->is_there_date_for_sending && $tag->date_for_sending_yearly) {
                    try {
                        $tagDate = Carbon::parse($tag->date_for_sending_yearly);
                        if ($tagDate->lt($weekStart->startOfDay())) {
                            return false;
                        }
                    } catch (\Exception $e) {
                         return false;
                    }
                }

                return true;
            });

            if ($veryHighTags->isEmpty()) continue;

            // Only pick ONE Very High tag to add per user request iteration? 
            // Or fill up to designs_count? 
            // "autoDistribute" iterates until designs_count is met.
            // But main subs only allow limit 1 Very High.
            
            // Filter out already used tags
            $availableVeryHighTags = $veryHighTags->filter(function($tag) use ($assignment) {
                return !ClientTagDistribution::where('client_designer_id', $assignment->id)
                    ->where('tag_id', $tag->id)
                    ->exists();
            });

            if ($availableVeryHighTags->isEmpty()) continue;

            // Pick one tag to distribute
            $tagToDistribute = $availableVeryHighTags->random();
            
            // Get Excluded Dates (Already used for this client/tag)
            $excludedDates = ClientTagDistribution::where('client_designer_id', $assignment->id)
                ->where('tag_id', $tagToDistribute->id)
                ->pluck('distribution_date')
                ->toArray();
            
            // Placement Logic
            $targetDate = null;
            $rank = 1;

            // 1. Check Sending Date Logic (Via Helper)
            if ($tagToDistribute->is_there_date_for_sending) {
                // Very High Lead: 1-2 Days
                $targetDate = $this->findBestDistributionDate(
                    $tagToDistribute, 
                    $weekStart, 
                    1, // Min Lead
                    2, // Max Lead
                    $dailyLoad, 
                    $days,
                    $excludedDates
                );
            }

            // 2. Fallback / Flexible Logic
            if (!$targetDate) {
                 if (!$tagToDistribute->is_there_date_for_sending) {
                     $clientUsage = $assignmentUsage[$assignment->id] ?? [];
                     $availableDays = array_diff($days, $excludedDates); // Filter allowed days

                     $emptyDays = array_diff($availableDays, array_keys($clientUsage));

                     if (!empty($emptyDays)) {
                        // Empty Day logic
                        $minLoad = PHP_INT_MAX; $bestDays = [];
                        foreach ($emptyDays as $day) {
                            $load = $dailyLoad[$day];
                            if ($load < $minLoad) { $minLoad = $load; $bestDays = [$day]; }
                            elseif ($load === $minLoad) { $bestDays[] = $day; }
                        }
                        $targetDate = $bestDays[array_rand($bestDays)];
                     } else {
                        // Merge Logic
                        $candidateDays = $availableDays;
                        $preferredDays = []; $acceptableDays = [];
                        foreach ($candidateDays as $day) {
                            $existingRanks = $clientUsage[$day] ?? [];
                            $hasVeryHigh = in_array(1, $existingRanks);
                            $hasHigh = in_array(2, $existingRanks);
                            if (!$hasVeryHigh && !$hasHigh) { $preferredDays[] = $day; }
                            else { $acceptableDays[] = $day; }
                        }
                        $finalCandidates = !empty($preferredDays) ? $preferredDays : $acceptableDays;
                         // Fallback to all allowed days if no preferred found, ensuring we don't pick excluded
                        if (empty($finalCandidates)) $finalCandidates = $availableDays;
                        
                        if (!empty($finalCandidates)) {
                            $minLoad = PHP_INT_MAX; $bestDays = [];
                            foreach ($finalCandidates as $day) {
                                $load = $dailyLoad[$day];
                                if ($load < $minLoad) { $minLoad = $load; $bestDays = [$day]; }
                                elseif ($load === $minLoad) { $bestDays[] = $day; }
                            }
                            $targetDate = $bestDays[array_rand($bestDays)];
                        }
                     }
                 }
            }

            if ($targetDate) {
                ClientTagDistribution::create([
                    'client_designer_id' => $assignment->id,
                    'tag_id' => $tagToDistribute->id,
                    'distribution_date' => $targetDate,
                    'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tagToDistribute->id, null, $this->selectedWeek),
                ]);

                $dailyLoad[$targetDate]++;
                $assignmentUsage[$assignment->id][$targetDate][] = $rank;
                $count++;
            }
        }

        if ($count > 0) {
            Notification::make()
                ->title('تم توزيع التاقات الهامة')
                ->body("تم توزيع {$count} تاق عالي الأهمية للمصمم.")
                ->success()
                ->send();
            
            $this->loadAssignments();
        } else {
             Notification::make()
                ->title('لا توجد تاقات متاحة')
                ->body("لم يتم العثور على تاقات يمكن توزيعها (طابق القواعد أو الحصة).")
                ->info()
                ->send();
        }
        
        $this->distributionStatus[$designerId]['very_high_processed'] = true;
    }

    public function distributeHighTagsForDesigner($designerId): void
    {
        $weekStart = Carbon::parse($this->selectedWeek);
        $days = $this->getWeekDays();
        
        $count = 0;
        $designerAssignments = $this->assignments->where('designer_id', $designerId);
        
        $dailyLoad = array_fill_keys($days, 0);
        $assignmentUsage = []; 

        // Initial Load Calculation
        foreach ($designerAssignments as $assignment) {
            foreach ($assignment->distributions as $dist) {
                if (in_array($dist->distribution_date, $days)) {
                    $dailyLoad[$dist->distribution_date]++;
                    
                    $tag = $dist->tag;
                    if ($tag) {
                        $importance = trim(strtolower($tag->importance ?? ''));
                        
                        $veryHighValues = ['very_high', 'very high', 'veryhigh', 'عالية جدا', 'عالية جداً', 'عالي جدا', 'عالي جداً', 'هام جدا', 'هام جداً'];
                        $highValues = ['high', 'عالية', 'عالي', 'هام'];
                        $mediumValues = ['medium', 'متوسطة', 'متوسط'];
                        $lowValues = ['low', 'منخفضة', 'منخفض'];

                        $rank = 5;
                        if (in_array($importance, $veryHighValues)) $rank = 1;
                        elseif (in_array($importance, $highValues)) $rank = 2;
                        elseif (in_array($importance, $mediumValues)) $rank = 3;
                        elseif (in_array($importance, $lowValues)) $rank = 4;

                        $assignmentUsage[$assignment->id][$dist->distribution_date][] = $rank;
                    }
                }
            }
        }

        foreach ($designerAssignments as $assignment) {
            // Check quota
            $designsCount = $assignment->subscription->designs_count ?? 0;
            $currentDistributionsCount = ClientTagDistribution::where('client_designer_id', $assignment->id)->count();
            
            if ($currentDistributionsCount >= $designsCount) {
                continue;
            }

            // Determine source of tags
            if ($assignment->subscription && !$assignment->subscription->is_main) {
                $tags = $assignment->subscription->tags ?? collect();
            } else {
                // Main Subscription Source: Hybrid Model
                $tags = $this->collectTagsForAssignment($assignment);
            }

            if ($tags->isEmpty()) continue;

            // Filter for High importance only
            $highTags = $tags->filter(function ($tag) use ($weekStart) {
                $importance = trim(strtolower($tag->importance ?? ''));
                $isHigh = in_array($importance, ['high', 'عالية', 'عالي', 'هام']);

                if (!$isHigh) return false;

                if ($tag->is_there_date_for_sending && $tag->date_for_sending_yearly) {
                    try {
                        // For basic expiration check, we can just ensure the date hasn't passed long ago
                        // But precise window calculation happens later.
                        $tagDate = Carbon::parse($tag->date_for_sending_yearly);
                        if ($tagDate->lt($weekStart->copy()->subDays(10))) {
                             return false; // Safely exclude very old tags
                        }
                    } catch (\Exception $e) {
                         return false;
                    }
                }
                return true;
            });

            if ($highTags->isEmpty()) continue;
            
            // Filter out already used tags
            $availableHighTags = $highTags->filter(function($tag) use ($assignment) {
                return !ClientTagDistribution::where('client_designer_id', $assignment->id)
                    ->where('tag_id', $tag->id)
                    ->exists();
            });

            if ($availableHighTags->isEmpty()) continue;

            // Pick one tag to distribute
            $tagToDistribute = $availableHighTags->random();
            
            // Get Excluded Dates
            $excludedDates = ClientTagDistribution::where('client_designer_id', $assignment->id)
                ->where('tag_id', $tagToDistribute->id)
                ->pluck('distribution_date')
                ->toArray();
            
            // Placement Logic
            $targetDate = null;
            $rank = 2; // High

            // 1. Check Sending Date Logic (Via Helper)
            if ($tagToDistribute->is_there_date_for_sending) {
                // High Lead: 2-5 Days
                $targetDate = $this->findBestDistributionDate(
                    $tagToDistribute, 
                    $weekStart, 
                    2, // Min Lead
                    5, // Max Lead
                    $dailyLoad, 
                    $days,
                    $excludedDates
                );
            }

            // 2. Fallback to Smart Placement (Flexible) if no date required or no valid window found 
            // (Only if not fixed date - logic says "distribute 3-5 days before sending date". 
            // If it has sending date but we can't meet condition, we probably shouldn't distribute it effectively. 
            // BUT if it has NO sending date, we use flexible logic.)
            if (!$targetDate && !$tagToDistribute->is_there_date_for_sending) {
                 $clientUsage = $assignmentUsage[$assignment->id] ?? [];
                 $availableDays = array_diff($days, $excludedDates); // Filter allowed days

                 $emptyDays = array_diff($availableDays, array_keys($clientUsage));

                 if (!empty($emptyDays)) {
                    $minLoad = PHP_INT_MAX;
                    $bestDays = [];
                    foreach ($emptyDays as $day) {
                        $load = $dailyLoad[$day];
                        if ($load < $minLoad) {
                            $minLoad = $load;
                            $bestDays = [$day];
                        } elseif ($load === $minLoad) {
                            $bestDays[] = $day;
                        }
                    }
                    $targetDate = !empty($bestDays) ? $bestDays[array_rand($bestDays)] : null;
                 } else {
                    $candidateDays = $availableDays;
                    $preferredDays = [];
                    $acceptableDays = [];

                    foreach ($candidateDays as $day) {
                        $existingRanks = $clientUsage[$day] ?? [];
                        
                        $hasVeryHigh = in_array(1, $existingRanks);
                        
                        if (!$hasVeryHigh) {
                            $preferredDays[] = $day;
                        } else {
                            $acceptableDays[] = $day;
                        }
                    }

                    $finalCandidates = !empty($preferredDays) ? $preferredDays : $acceptableDays;
                    if (empty($finalCandidates)) $finalCandidates = $availableDays;

                    if (!empty($finalCandidates)) {
                        $minLoad = PHP_INT_MAX;
                        $bestDays = [];
                        foreach ($finalCandidates as $day) {
                            $load = $dailyLoad[$day];
                            if ($load < $minLoad) {
                                $minLoad = $load;
                                $bestDays = [$day];
                            } elseif ($load === $minLoad) {
                                $bestDays[] = $day;
                            }
                        }
                        $targetDate = $bestDays[array_rand($bestDays)];
                    }
                 }
            }

            if ($targetDate) {
                ClientTagDistribution::create([
                    'client_designer_id' => $assignment->id,
                    'tag_id' => $tagToDistribute->id,
                    'distribution_date' => $targetDate,
                    'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tagToDistribute->id, null, $this->selectedWeek),
                ]);

                $dailyLoad[$targetDate]++;
                $assignmentUsage[$assignment->id][$targetDate][] = $rank;
                $count++;
            }
        }

        if ($count > 0) {
            Notification::make()
                ->title('تم توزيع التاقات العالية')
                ->body("تم توزيع {$count} تاق عالي الأهمية للمصمم.")
                ->success()
                ->send();
            
            $this->loadAssignments();
        } else {
             Notification::make()
                ->title('لا توجد تاقات متاحة')
                ->body("لم يتم العثور على تاقات عالية يمكن توزيعها وفق القواعد (مثلاً: قبل موعد الإرسال بـ 3-5 أيام).")
                ->info()
                ->send();
        }
        
        $this->distributionStatus[$designerId]['high_processed'] = true;
    }

    public function distributeMediumLowTagsForDesigner($designerId): void
    {
        $weekStart = Carbon::parse($this->selectedWeek);
        $days = $this->getWeekDays();
        
        $count = 0;
        $designerAssignments = $this->assignments->where('designer_id', $designerId);
        
        $dailyLoad = array_fill_keys($days, 0);
        $assignmentUsage = []; 

        // Initial Load Calculation
        foreach ($designerAssignments as $assignment) {
            foreach ($assignment->distributions as $dist) {
                if (in_array($dist->distribution_date, $days)) {
                    $dailyLoad[$dist->distribution_date]++;
                    
                    $tag = $dist->tag;
                    if ($tag) {
                        $importance = trim(strtolower($tag->importance ?? ''));
                        
                        $veryHighValues = ['very_high', 'very high', 'veryhigh', 'عالية جدا', 'عالية جداً', 'عالي جدا', 'عالي جداً', 'هام جدا', 'هام جداً'];
                        $highValues = ['high', 'عالية', 'عالي', 'هام'];
                        $mediumValues = ['medium', 'متوسطة', 'متوسط'];
                        $lowValues = ['low', 'منخفضة', 'منخفض'];

                        $rank = 5;
                        if (in_array($importance, $veryHighValues)) $rank = 1;
                        elseif (in_array($importance, $highValues)) $rank = 2;
                        elseif (in_array($importance, $mediumValues)) $rank = 3;
                        elseif (in_array($importance, $lowValues)) $rank = 4;

                        $assignmentUsage[$assignment->id][$dist->distribution_date][] = $rank;
                    }
                }
            }
        }

        foreach ($designerAssignments as $assignment) {
            // Check quota setup
            $designsCount = $assignment->subscription->designs_count ?? 0;
            
            // Determine source of tags
            if ($assignment->subscription && !$assignment->subscription->is_main) {
                $tags = $assignment->subscription->tags ?? collect();
            } else {
                // Main Subscription Source: Hybrid Model
                $tags = $this->collectTagsForAssignment($assignment);
            }

            if ($tags->isEmpty()) continue;

            // Filter for Medium/Low importance only
            $mediumLowTags = $tags->filter(function ($tag) use ($weekStart) {
                $importance = trim(strtolower($tag->importance ?? ''));
                $isMedium = in_array($importance, ['medium', 'متوسطة', 'متوسط']);
                $isLow = in_array($importance, ['low', 'منخفضة', 'منخفض']);

                if (!$isMedium && !$isLow) return false;

                if ($tag->is_there_date_for_sending && $tag->date_for_sending_yearly) {
                    try {
                        $tagDate = Carbon::parse($tag->date_for_sending_yearly);
                        if ($tagDate->lt($weekStart->copy()->subDays(10))) {
                             return false;
                        }
                    } catch (\Exception $e) {
                         return false;
                    }
                }
                return true;
            });

            if ($mediumLowTags->isEmpty()) continue;

            // LOOP until distinct count reached or no valid tags
            while (true) {
                $currentDistributionsCount = ClientTagDistribution::where('client_designer_id', $assignment->id)->count();
                if ($currentDistributionsCount >= $designsCount) {
                    break; 
                }

                $availableTags = $mediumLowTags->filter(function($tag) use ($assignment) {
                    return !ClientTagDistribution::where('client_designer_id', $assignment->id)
                        ->where('tag_id', $tag->id)
                        ->exists();
                });
                
                // If we ran out of unique tags, allow repetition to fill the quota
                if ($availableTags->isEmpty()) {
                    $availableTags = $mediumLowTags;
                }
                
                if ($availableTags->isEmpty()) {
                    break;
                }

                $tagToDistribute = $availableTags->random();
                
                // Get Excluded Dates
                $excludedDates = ClientTagDistribution::where('client_designer_id', $assignment->id)
                    ->where('tag_id', $tagToDistribute->id)
                    ->pluck('distribution_date')
                    ->toArray();
                
                // Placement Logic
                $targetDate = null;
                $rank = 3; 

                // 1. Check Fixed Date (Via Helper)
                if ($tagToDistribute->is_there_date_for_sending) {
                    // Med/Low Lead: 1-2 Days
                    $targetDate = $this->findBestDistributionDate(
                        $tagToDistribute, 
                        $weekStart, 
                        1, // Min Lead
                        2, // Max Lead
                        $dailyLoad, 
                        $days,
                        $excludedDates
                    );
                }

                // 2. Smart Placement (Flexible)
                if (!$targetDate) {
                     $clientUsage = $assignmentUsage[$assignment->id] ?? [];
                     $availableDays = array_diff($days, $excludedDates); // Filter allowed days

                     $emptyDays = array_diff($availableDays, array_keys($clientUsage));

                     if (!empty($emptyDays)) {
                        $minLoad = PHP_INT_MAX;
                        $bestDays = [];
                        foreach ($emptyDays as $day) {
                            $load = $dailyLoad[$day];
                            if ($load < $minLoad) {
                                $minLoad = $load;
                                $bestDays = [$day];
                            } elseif ($load === $minLoad) {
                                $bestDays[] = $day;
                            }
                        }
                        $targetDate = !empty($bestDays) ? $bestDays[array_rand($bestDays)] : null;
                     } else {
                        // Merge strategy
                        $minLoad = PHP_INT_MAX;
                        $bestDays = [];
                        foreach ($availableDays as $day) {
                            $load = $dailyLoad[$day];
                            if ($load < $minLoad) {
                                $minLoad = $load;
                                $bestDays = [$day];
                            } elseif ($load === $minLoad) {
                                $bestDays[] = $day;
                            }
                        }
                        $targetDate = !empty($bestDays) ? $bestDays[array_rand($bestDays)] : null;
                     }
                }

                if ($targetDate) {
                    ClientTagDistribution::create([
                        'client_designer_id' => $assignment->id,
                        'tag_id' => $tagToDistribute->id,
                        'distribution_date' => $targetDate,
                        'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tagToDistribute->id, null, $this->selectedWeek),
                    ]);

                    $dailyLoad[$targetDate]++;
                    $assignmentUsage[$assignment->id][$targetDate][] = $rank;
                    $count++;
                } else {
                    break; 
                }
            }
        }

        if ($count > 0) {
            Notification::make()
                ->title('تم توزيع التاقات المتوسطة/المنخفضة')
                ->body("تم توزيع {$count} تاق.")
                ->success()
                ->send();
            
            $this->loadAssignments();
        } else {
             Notification::make()
                ->title('لا توجد تاقات متاحة')
                ->body("لم يتم العثور على تاقات يمكن توزيعها.")
                ->info()
                ->send();
        }
    }

    public function hasDistributedVeryHigh($designerId): bool
    {
        if ($this->distributionStatus[$designerId]['very_high_processed'] ?? false) {
            return true;
        }

        $assignments = $this->assignments->where('designer_id', $designerId);
        $days = $this->getWeekDays();

        foreach ($assignments as $assignment) {
            foreach ($assignment->distributions as $dist) {
                if (in_array($dist->distribution_date, $days) && $dist->tag) {
                    $importance = trim(strtolower($dist->tag->importance ?? ''));
                    if (in_array($importance, [
                        'very_high', 'very high', 'veryhigh',
                        'عالية جدا', 'عالية جداً', 'عالي جدا', 'عالي جداً',
                        'هام جدا', 'هام جداً'
                    ])) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function hasDistributedHigh($designerId): bool
    {
        if ($this->distributionStatus[$designerId]['high_processed'] ?? false) {
            return true;
        }

        $assignments = $this->assignments->where('designer_id', $designerId);
        $days = $this->getWeekDays();

        foreach ($assignments as $assignment) {
            foreach ($assignment->distributions as $dist) {
                if (in_array($dist->distribution_date, $days) && $dist->tag) {
                    $importance = trim(strtolower($dist->tag->importance ?? ''));
                    if (in_array($importance, ['high', 'عالية', 'عالي', 'هام'])) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    protected function findBestDistributionDate($tag, $weekStart, $minLead, $maxLead, $dailyLoad, $workingDays, $excludedDates = [])
    {
        $candidateSendingDates = [];
        // Check 30 days ahead to capture deadlines that might require work this week
        $checkStart = $weekStart->copy(); 
        
        for ($i = 0; $i < 30; $i++) {
            $potentialDate = $checkStart->copy()->addDays($i);
            $isMatch = false;

            // Weekly Check (Array support)
            if (!empty($tag->weekly_day)) {
                $daysArray = is_array($tag->weekly_day) ? $tag->weekly_day : [$tag->weekly_day];
                
                // Normalize potential date day name
                $potentialDayName = $potentialDate->format('l'); // English
                $potentialDayNameAr = $potentialDate->locale('ar')->translatedFormat('l'); // Arabic

                foreach ($daysArray as $d) {
                    $d = trim($d);
                    if (strcasecmp($potentialDayName, $d) === 0 || $potentialDayNameAr === $d) {
                        $isMatch = true;
                        break;
                    }
                }
            }

            // Yearly Check
            if ($tag->date_for_sending_yearly) {
                try {
                    $tagDate = Carbon::parse($tag->date_for_sending_yearly);
                    if ($potentialDate->month === $tagDate->month && $potentialDate->day === $tagDate->day) {
                        $isMatch = true;
                    }
                } catch (\Exception $e) {}
            }

            if ($isMatch) {
                $candidateSendingDates[] = $potentialDate;
            }
        }

        $possibleWindowsEncoded = []; // To dedup dates
        
        foreach ($candidateSendingDates as $sDate) {
            // Calculate Window [S - Max, S - Min]
            // Example: S=Friday, Min=1, Max=2. Window = [Thu, Wed].
            $minWindow = $sDate->copy()->subDays($maxLead); 
            $maxWindow = $sDate->copy()->subDays($minLead);

            // Find Intersection with Current Working Week ($workingDays)
            foreach ($workingDays as $wdStr) {
                $wd = Carbon::parse($wdStr);
                if ($wd->betweenIncluded($minWindow, $maxWindow)) {
                    $possibleWindowsEncoded[] = $wdStr;
                }
            }
        }

        $validDates = array_unique($possibleWindowsEncoded);
        
        // Filter exclusions (Client Specific Uniqueness)
        $validDates = array_diff($validDates, $excludedDates);

        if (empty($validDates)) {
            return null;
        }

        // Pick best day (lowest load)
        $minLoadVal = PHP_INT_MAX;
        $bestDays = [];
        
        foreach ($validDates as $day) {
            $load = $dailyLoad[$day] ?? 0;
            if ($load < $minLoadVal) {
                $minLoadVal = $load;
                $bestDays = [$day];
            } elseif ($load === $minLoadVal) {
                $bestDays[] = $day;
            }
        }

        return !empty($bestDays) ? $bestDays[array_rand($bestDays)] : null;
    }

    protected function calculateScheduledSendingAt($distributionDate, $tagId, $ideaId, $selectedWeek): ?string
    {
        $idea = $ideaId ? Idea::find($ideaId) : null;
        if ($idea && $idea->scheduled_at) {
            return $idea->scheduled_at;
        }

        $tag = $tagId ? Tag::find($tagId) : null;
        if ($tag && $tag->weekly_time) {
            try {
                $baseDate = Carbon::parse($distributionDate);
                $importance = trim(strtolower($tag->importance ?? ''));
                $veryHighValues = [
                    'very_high', 'very high', 'veryhigh',
                    'عالية جدا', 'عالية جداً', 'عالي جدا', 'عالي جداً',
                    'هام جدا', 'هام جداً'
                ];
                $isVeryHigh = in_array($importance, $veryHighValues);

                $targetDate = null;

                if ($isVeryHigh && !empty($tag->weekly_day)) {
                    $weekStart = Carbon::parse($selectedWeek);
                    $targetDayNames = is_array($tag->weekly_day) ? $tag->weekly_day : [$tag->weekly_day];
                    
                    for($i=0; $i<7; $i++) {
                        $potentialDate = $weekStart->copy()->addDays($i);
                        $potentialDayName = $potentialDate->translatedFormat('l');
                        $potentialDayNameEn = $potentialDate->format('l');
                        
                        foreach($targetDayNames as $targetName) {
                            if (strcasecmp($targetName, $potentialDayName) === 0 || strcasecmp($targetName, $potentialDayNameEn) === 0) {
                                $targetDate = $potentialDate;
                                break 2;
                            }
                        }
                    }
                }

                if (!$targetDate) {
                    $targetDate = $baseDate->addDay();
                }

                return $targetDate->format('Y-m-d') . ' ' . Carbon::parse($tag->weekly_time)->format('H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
