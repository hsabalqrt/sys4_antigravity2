<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Designer;
use App\Models\ClientDesigner;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * خدمة لتوزيع المصممين على العملاء بشكل تلقائي.
 *
 * تحتوي هذه الخدمة على منطق حساب النقاط وتوزيع الاشتراكات على
 * المصممين المتاحين بناءً على مجموعة من المعايير.
 */
class DesignerDistributionService
{
    /**
     * أوزان المعايير المستخدمة في حساب نقاط المصممين.
     */
    private const WEIGHTS = [
        'specialization' => 30,      // التخصص (تم التقليل من 40)
        'capacity_balance' => 30,    // توازن السعة (تم الرفع من 5 لضمان التوزيع العادل)
        'rating' => 30,              // التقييم (تم التقليل من 25)
        'continuity' => 15,          // الاستمرارية (تم التقليل من 20)
        'experience' => 10,          // الخبرة
    ];

    /**
     * يقوم بتوزيع الاشتراكات التي تحتاج إلى مصممين بشكل تلقائي.
     *
     * @param  string  $weekStartDate تاريخ بداية الأسبوع (Y-m-d).
     * @param  array  $options خيارات إضافية مثل `force` لتجاهل التوزيعات الحالية.
     * @return array نتيجة عملية التوزيع.
     */
    public function autoDistribute(string $weekStartDate, array $options = []): array
    {
        try {
            DB::beginTransaction();

            // التحقق من صحة التاريخ
            $weekStart = Carbon::parse($weekStartDate)->startOfDay();
            $force = $options['force'] ?? false;

            // جلب البيانات
            $designers = $this->getAvailableDesigners();
            $subscriptions = $this->getSubscriptionsNeedingDistribution($weekStart, $force);

            // التحقق من وجود بيانات
            if ($designers->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'لا يوجد مصممين متاحين للتوزيع',
                    'distributed' => 0,
                    'failed' => $subscriptions->count(),
                    'details' => [],
                ];
            }

            if ($subscriptions->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'لا يوجد اشتراكات تحتاج للتوزيع',
                    'distributed' => 0,
                    'failed' => 0,
                    'details' => [],
                ];
            }

            // تهيئة متتبعات الحمل
            $designerLoads = $this->initializeDesignerLoads($designers);

            // ترتيب الاشتراكات حسب الأولوية (الأكبر حجماً أولاً)
            $sortedSubscriptions = $this->sortSubscriptionsByPriority($subscriptions);

            // تنفيذ التوزيع
            $results = $this->performDistribution(
                $sortedSubscriptions,
                $designers,
                $designerLoads,
                $weekStart
            );

            DB::commit();

            return $this->formatResults($results, $subscriptions->count());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في التوزيع التلقائي: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء التوزيع: ' . $e->getMessage(),
                'distributed' => 0,
                'failed' => 0,
                'details' => [],
            ];
        }
    }

    /**
     * يجلب المصممين المتاحين مع علاقاتهم.
     *
     * @return \Illuminate\Support\Collection
     */
    private function getAvailableDesigners(): Collection
    {
        return Designer::with(['categories', 'user'])
            ->whereNotNull('max_capacity')
            ->where('max_capacity', '>', 0)
            ->get()
            ->filter(function ($designer) {
                // التأكد من وجود بيانات المستخدم
                return $designer->user !== null;
            });
    }

    /**
     * يجلب الاشتراكات التي تحتاج إلى توزيع لهذا الأسبوع.
     *
     * @param  \Carbon\Carbon  $weekStart
     * @param  bool  $force
     * @return \Illuminate\Support\Collection
     */
    private function getSubscriptionsNeedingDistribution(Carbon $weekStart, bool $force = false): Collection
    {
        $weekStartDate = $weekStart->format('Y-m-d');
        $weekEndDate = $weekStart->copy()->addDays(6)->format('Y-m-d');

        $query = Subscription::with(['client.category'])
            ->where('status', 'active')
            ->where('start_date', '<=', $weekEndDate);

        // إذا لم يكن force، استبعد الاشتراكات الموزعة بالفعل
        if (!$force) {
            // جلب IDs الاشتراكات التي تم توزيعها بالفعل لهذا الأسبوع
            $distributedSubscriptionIds = ClientDesigner::where('week_start_date', $weekStartDate)
                ->whereNotNull('subscription_id')
                ->pluck('subscription_id')
                ->toArray();

            // استبعاد الاشتراكات التي تم توزيعها بالفعل
            $query->whereNotIn('id', $distributedSubscriptionIds);
        }

        return $query->get()
            ->filter(function ($subscription) {
                // التأكد من وجود عميل
                return $subscription->client !== null;
            });
    }

    /**
     * يقوم بتهيئة الأحمال الأولية للمصممين.
     *
     * @param  \Illuminate\Support\Collection  $designers
     * @return array
     */
    private function initializeDesignerLoads(Collection $designers): array
    {
        $loads = [];
        foreach ($designers as $designer) {
            $loads[$designer->id] = [
                'current' => 0,
                'min' => $designer->min_capacity ?? 0,
                'max' => $designer->max_capacity ?? 0,
                'designs_count' => 0,
            ];
        }
        return $loads;
    }

    /**
     * يقوم بترتيب الاشتراكات حسب الأولوية (الأعلى أولاً).
     *
     * @param  \Illuminate\Support\Collection  $subscriptions
     * @return \Illuminate\Support\Collection
     */
    private function sortSubscriptionsByPriority(Collection $subscriptions): Collection
    {
        return $subscriptions->sortByDesc(function ($subscription) {
            return $subscription->designs_count ?? 0;
        });
    }

    /**
     * يقوم بتنفيذ عملية التوزيع الفعلية.
     *
     * @param  \Illuminate\Support\Collection  $subscriptions
     * @param  \Illuminate\Support\Collection  $designers
     * @param  array  &$designerLoads
     * @param  \Carbon\Carbon  $weekStart
     * @return array
     */
    private function performDistribution(
        Collection $subscriptions,
        Collection $designers,
        array &$designerLoads,
        Carbon $weekStart
    ): array {
        $results = [
            'distributed' => [],
            'failed' => [],
        ];

        // 1. توزيع العملاء المثبتين (Pinned Clients)
        $this->distributePinnedClients(
            $subscriptions,
            $designers,
            $designerLoads,
            $weekStart,
            $results
        );

        // مرحلة التوزيع الأولي لضمان تضمين جميع المصممين إذا أمكن
        $this->initialDesignerInclusion(
            $subscriptions,
            $designers,
            $designerLoads,
            $weekStart,
            $results
        );

        // إزالة الاشتراكات الموزعة في المرحلة الأولية
        $remainingSubscriptions = $subscriptions->reject(function ($subscription) {
            return isset($subscription->assigned_in_initial);
        });

        // التوزيع العادي للاشتراكات المتبقية
        foreach ($remainingSubscriptions as $subscription) {
            $bestDesigner = $this->findBestDesigner(
                $subscription,
                $designers,
                $designerLoads,
                $weekStart
            );

            if ($bestDesigner) {
                // إنشاء أو تحديث التعيين
                $assignment = $this->assignSubscriptionToDesigner(
                    $subscription,
                    $bestDesigner,
                    $weekStart
                );

                // تحديث الحمل
                $designsCount = $subscription->designs_count ?? 0;
                $designerLoads[$bestDesigner->id]['current']++;
                $designerLoads[$bestDesigner->id]['designs_count'] += $designsCount;

                $results['distributed'][] = [
                    'client_id' => $subscription->client_id,
                    'subscription_id' => $subscription->id,
                    'client_name' => $subscription->client->client_name ?? $subscription->client->company,
                    'subscription_type' => $subscription->is_main ? 'أساسي' : 'ثانوي',
                    'designer_id' => $bestDesigner->id,
                    'designer_name' => $bestDesigner->user->name,
                    'designs_count' => $designsCount,
                    'score' => $assignment['score'] ?? 0,
                ];
            } else {
                $results['failed'][] = [
                    'client_id' => $subscription->client_id,
                    'client_name' => $subscription->client->client_name ?? $subscription->client->company,
                    'reason' => 'لا يوجد مصمم متاح بسعة كافية',
                ];
            }
        }

        return $results;
    }

    /**
     * توزيع العملاء المثبتين على مصممين محددين.
     *
     * @param  \Illuminate\Support\Collection  $subscriptions
     * @param  \Illuminate\Support\Collection  $designers
     * @param  array  &$designerLoads
     * @param  \Carbon\Carbon  $weekStart
     * @param  array  &$results
     * @return void
     */
    private function distributePinnedClients(
        Collection $subscriptions,
        Collection $designers,
        array &$designerLoads,
        Carbon $weekStart,
        array &$results
    ): void {
        foreach ($subscriptions as $subscription) {
            // تخطي إذا تم تعيينه بالفعل (احتياطي)
            if (isset($subscription->assigned_in_initial)) {
                continue;
            }

            $fixedDesignerId = $subscription->client->fixed_designer_id ?? null;

            if ($fixedDesignerId) {
                $designer = $designers->firstWhere('id', $fixedDesignerId);

                if ($designer) {
                    // تعيين الاشتراك للمصمم المثبت
                    $assignment = $this->assignSubscriptionToDesigner(
                        $subscription,
                        $designer,
                        $weekStart
                    );

                    // تحديث الحمل
                    $designsCount = $subscription->designs_count ?? 0;
                    $designerLoads[$designer->id]['current']++;
                    $designerLoads[$designer->id]['designs_count'] += $designsCount;

                    $results['distributed'][] = [
                        'client_id' => $subscription->client_id,
                        'subscription_id' => $subscription->id,
                        'client_name' => $subscription->client->client_name ?? $subscription->client->company,
                        'subscription_type' => $subscription->is_main ? 'أساسي' : 'ثانوي',
                        'designer_id' => $designer->id,
                        'designer_name' => $designer->user->name,
                        'designs_count' => $designsCount,
                        'score' => 100, // درجة كاملة للتثبيت
                        'reason' => 'pinned',
                    ];

                    // وسم الاشتراك بأنه تم توزيعه
                    $subscription->assigned_in_initial = true;
                } else {
                    $results['failed'][] = [
                        'client_id' => $subscription->client_id,
                        'client_name' => $subscription->client->client_name ?? $subscription->client->company,
                        'reason' => 'المصمم المثبت غير متاح أو غير موجود',
                    ];
                }
            }
        }
    }

    /**
     * مرحلة التوزيع الأولي لضمان تضمين كل مصمم واحد على الأقل.
     *
     * @param  \Illuminate\Support\Collection  $subscriptions
     * @param  \Illuminate\Support\Collection  $designers
     * @param  array  &$designerLoads
     * @param  \Carbon\Carbon  $weekStart
     * @param  array  &$results
     * @return void
     */
    private function initialDesignerInclusion(
        Collection $subscriptions,
        Collection $designers,
        array &$designerLoads,
        Carbon $weekStart,
        array &$results
    ): void {
        if ($subscriptions->count() < $designers->count()) {
            // إذا كان عدد الاشتراكات أقل، وزع على أكبر عدد ممكن (الأقل حملاً أولاً)
            $this->distributeToLeastLoaded($subscriptions, $designers, $designerLoads, $weekStart, $results);
            return;
        }

        // ترتيب الاشتراكات الصغيرة أولاً للتوزيع الأولي
        $smallSubscriptions = $subscriptions->sortBy(function ($sub) {
            return $sub->designs_count ?? 999;
        })->values()->take($designers->count());

        // ترتيب المصممين حسب الدرجة العامة (للتوزيع العادل)
        $sortedDesigners = $designers->sortByDesc(function ($designer) {
            return $this->calculateGeneralDesignerScore($designer);
        })->values();

        $subscriptionIndex = 0;
        foreach ($sortedDesigners as $designer) {
            if ($subscriptionIndex >= $smallSubscriptions->count()) {
                break;
            }

            $subscription = $smallSubscriptions[$subscriptionIndex];
            $subscriptionIndex++;

            // التحقق من السعة
            if (!$this->hasAvailableCapacity($designer, $designerLoads[$designer->id], $subscription)) {
                $results['failed'][] = [
                    'client_id' => $subscription->client_id,
                    'client_name' => $subscription->client->client_name ?? $subscription->client->company,
                    'reason' => 'لا يوجد سعة كافية في المرحلة الأولية',
                ];
                continue;
            }

            // تعيين الاشتراك
            $this->assignSubscriptionToDesigner($subscription, $designer, $weekStart);

            // تحديث الحمل
            $designsCount = $subscription->designs_count ?? 0;
            $designerLoads[$designer->id]['current']++;
            $designerLoads[$designer->id]['designs_count'] += $designsCount;

            // إضافة إلى النتائج وعلامة للإزالة
            $results['distributed'][] = [
                'client_id' => $subscription->client_id,
                'subscription_id' => $subscription->id,
                'client_name' => $subscription->client->client_name ?? $subscription->client->company,
                'subscription_type' => $subscription->is_main ? 'أساسي' : 'ثانوي',
                'designer_id' => $designer->id,
                'designer_name' => $designer->user->name,
                'designs_count' => $designsCount,
                'score' => 0, // درجة أولية
                'phase' => 'initial',
            ];

            $subscription->assigned_in_initial = true;
        }
    }

    /**
     * توزيع على المصممين الأقل حملاً عندما يكون عدد الاشتراكات أقل.
     *
     * @param  \Illuminate\Support\Collection  $subscriptions
     * @param  \Illuminate\Support\Collection  $designers
     * @param  array  &$designerLoads
     * @param  \Carbon\Carbon  $weekStart
     * @param  array  &$results
     * @return void
     */
    private function distributeToLeastLoaded(
        Collection $subscriptions,
        Collection $designers,
        array &$designerLoads,
        Carbon $weekStart,
        array &$results
    ): void {
        // ترتيب المصممين حسب الحمل الصاعد
        $sortedDesigners = $designers->sortBy(function ($designer) use ($designerLoads) {
            return $designerLoads[$designer->id]['current'];
        });

        foreach ($subscriptions as $subscription) {
            $bestDesigner = null;
            foreach ($sortedDesigners as $designer) {
                if ($this->hasAvailableCapacity($designer, $designerLoads[$designer->id], $subscription)) {
                    $bestDesigner = $designer;
                    break;
                }
            }

            if ($bestDesigner) {
                $this->assignSubscriptionToDesigner($subscription, $bestDesigner, $weekStart);
                $designsCount = $subscription->designs_count ?? 0;
                $designerLoads[$bestDesigner->id]['current']++;
                $designerLoads[$bestDesigner->id]['designs_count'] += $designsCount;

                $results['distributed'][] = [
                    'client_id' => $subscription->client_id,
                    'subscription_id' => $subscription->id,
                    'client_name' => $subscription->client->client_name ?? $subscription->client->company,
                    'subscription_type' => $subscription->is_main ? 'أساسي' : 'ثانوي',
                    'designer_id' => $bestDesigner->id,
                    'designer_name' => $bestDesigner->user->name,
                    'designs_count' => $designsCount,
                    'score' => 0,
                    'phase' => 'limited',
                ];

                $subscription->assigned_in_initial = true;
            } else {
                $results['failed'][] = [
                    'client_id' => $subscription->client_id,
                    'client_name' => $subscription->client->client_name ?? $subscription->client->company,
                    'reason' => 'لا يوجد سعة كافية',
                ];
            }
        }
    }

    /**
     * حساب درجة عامة للمصمم للتوزيع الأولي.
     *
     * @param  \App\Models\Designer  $designer
     * @return float
     */
    private function calculateGeneralDesignerScore(Designer $designer): float
    {
        // درجة بسيطة بناءً على التقييم والخبرة
        $ratingScore = $this->calculateRatingScore($designer);
        $experienceScore = min(1.0, ($designer->amount_of_designs ?? 0) / 1000);

        return ($ratingScore * 0.6) + ($experienceScore * 0.4);
    }

    /**
     * يبحث عن أفضل مصمم مناسب للاشتراك المحدد.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \Illuminate\Support\Collection  $designers
     * @param  array  $designerLoads
     * @param  \Carbon\Carbon  $weekStart
     * @return \App\Models\Designer|null
     */
    private function findBestDesigner(
        Subscription $subscription,
        Collection $designers,
        array $designerLoads,
        Carbon $weekStart
    ): ?Designer {
        $bestDesigner = null;
        $bestScore = -1;

        // الحصول على المصمم السابق للاستمرارية (نبحث عن نفس الاشتراك أو نفس العميل)
        $previousDesigner = $this->getPreviousDesigner($subscription, $weekStart);

        foreach ($designers as $designer) {
            // التحقق من السعة المتاحة
            if (!$this->hasAvailableCapacity($designer, $designerLoads[$designer->id], $subscription)) {
                continue;
            }

            // حساب النقاط
            $score = $this->calculateDesignerScore(
                $designer,
                $subscription,
                $designerLoads[$designer->id],
                $previousDesigner
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDesigner = $designer;
            }
        }

        return $bestDesigner;
    }

    /**
     * يتحقق مما إذا كان لدى المصمم سعة متاحة لاستيعاب اشتراك جديد.
     *
     * @param  \App\Models\Designer  $designer
     * @param  array  $load
     * @param  \App\Models\Subscription  $subscription
     * @return bool
     */
    private function hasAvailableCapacity(Designer $designer, array $load, Subscription $subscription): bool
    {
        $maxCapacity = $designer->max_capacity ?? 0;
        $currentDesignsCount = $load['designs_count'];
        $requiredDesigns = $subscription->designs_count ?? 0;

        // التحقق من مجموع عدد التصاميم بدلاً من عدد العملاء
        if (($currentDesignsCount + $requiredDesigns) > $maxCapacity) {
            return false;
        }

        return true;
    }

    /**
     * يحسب النقاط الإجمالية لمصمم معين بناءً على اشتراك محدد.
     *
     * @param  \App\Models\Designer  $designer
     * @param  \App\Models\Subscription  $subscription
     * @param  array  $load
     * @param  int|null  $previousDesignerId
     * @return float
     */
    private function calculateDesignerScore(
        Designer $designer,
        Subscription $subscription,
        array $load,
        ?int $previousDesignerId
    ): float {
        $scores = [];
        $client = $subscription->client;

        // 1. نقاط التخصص (40 نقطة)
        $scores['specialization'] = $this->calculateSpecializationScore($designer, $client);

        // 2. نقاط التقييم (25 نقطة)
        $scores['rating'] = $this->calculateRatingScore($designer);

        // 3. نقاط الاستمرارية (20 نقطة)
        $scores['continuity'] = $this->calculateContinuityScore($designer, $previousDesignerId);

        // 4. نقاط الخبرة (10 نقطة)
        $scores['experience'] = $this->calculateExperienceScore($designer, $subscription);

        // 5. نقاط توازن السعة (5 نقاط)
        $scores['capacity_balance'] = $this->calculateCapacityBalanceScore($designer, $load);

        // حساب المجموع الكلي
        $totalScore = 0;
        foreach ($scores as $key => $score) {
            $weight = self::WEIGHTS[$key] ?? 0;
            $totalScore += ($score * $weight);
        }

        return $totalScore;
    }

    /**
     * يحسب نقاط التخصص بناءً على تطابق فئة العميل مع فئات المصمم.
     *
     * @param  \App\Models\Designer  $designer
     * @param  \App\Models\Client  $client
     * @return float
     */
    private function calculateSpecializationScore(Designer $designer, Client $client): float
    {
        if (!$client->category_id) {
            return 0.5; // نقاط محايدة للعملاء بدون تصنيف
        }

        $designerCategories = $designer->categories->pluck('id')->toArray();

        if (in_array($client->category_id, $designerCategories)) {
            return 1.0; // تطابق كامل
        }

        return 0.3; // لا يوجد تطابق
    }

    /**
     * يحسب نقاط التقييم للمصمم.
     *
     * @param  \App\Models\Designer  $designer
     * @return float
     */
    private function calculateRatingScore(Designer $designer): float
    {
        $rate = $designer->rate ?? 0;
        $maxRate = 10; // افتراض أن التقييم من 10

        if ($maxRate == 0) {
            return 0.5;
        }

        return min(1.0, $rate / $maxRate);
    }

    /**
     * يحسب نقاط الاستمرارية بناءً على ما إذا كان المصمم هو نفسه المصمم السابق.
     *
     * @param  \App\Models\Designer  $designer
     * @param  int|null  $previousDesignerId
     * @return float
     */
    private function calculateContinuityScore(Designer $designer, ?int $previousDesignerId): float
    {
        if ($previousDesignerId === null) {
            return 0.5; // محايد للعملاء الجدد
        }

        return $designer->id === $previousDesignerId ? 1.0 : 0.0;
    }

    /**
     * يحسب نقاط الخبرة بناءً على حجم الاشتراك وخبرة المصمم.
     *
     * @param  \App\Models\Designer  $designer
     * @param  \App\Models\Subscription  $subscription
     * @return float
     */
    private function calculateExperienceScore(Designer $designer, Subscription $subscription): float
    {
        $designerExperience = $designer->amount_of_designs ?? 0;
        $designsNeed = $subscription->designs_count ?? 0;

        // المصممين ذوي الخبرة الأعلى يحصلون على نقاط أعلى للاشتراكات الكبيرة
        if ($designsNeed > 20) {
            // اشتراك كبير - يحتاج مصمم ذو خبرة
            return min(1.0, $designerExperience / 1000);
        } else {
            // اشتراك صغير - أي مصمم مناسب
            return 0.7;
        }
    }

    /**
     * يحسب نقاط توازن السعة لتشجيع التوزيع العادل.
     *
     * @param  \App\Models\Designer  $designer
     * @param  array  $load
     * @return float
     */
    private function calculateCapacityBalanceScore(Designer $designer, array $load): float
    {
        $minCapacity = $load['min'];
        $maxCapacity = $load['max'];
        $currentDesignsCount = $load['designs_count'];
        $currentAssignments = $load['current'];

        if ($maxCapacity == 0) {
            return 0;
        }

        // إعطاء نقاط إضافية عالية للمصممين بدون تعيينات لضمان التضمين
        if ($currentAssignments == 0) {
            return 1.5; // دفع قوي للتضمين
        }

        // تشجيع الوصول إلى الحد الأدنى أولاً
        if ($currentDesignsCount < $minCapacity) {
            return 1.0;
        }

        // بعد الحد الأدنى، تقليل النقاط تدريجياً
        $utilizationRate = $currentDesignsCount / $maxCapacity;
        return max(0, 1.0 - $utilizationRate);
    }

    /**
     * يحصل على معرف المصمم السابق للاشتراك أو العميل.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \Carbon\Carbon  $currentWeekStart
     * @return int|null
     */
    private function getPreviousDesigner(Subscription $subscription, Carbon $currentWeekStart): ?int
    {
        $previousWeekStart = $currentWeekStart->copy()->subWeek()->format('Y-m-d');

        // البحث عن تعيين سابق لنفس الاشتراك
        $previousAssignment = ClientDesigner::where('subscription_id', $subscription->id)
            ->where('week_start_date', $previousWeekStart)
            ->first();

        if ($previousAssignment) {
            return $previousAssignment->designer_id;
        }

        // إذا لم يوجد، البحث عن تعيين سابق لنفس العميل (لأي اشتراك)
        $previousClientAssignment = ClientDesigner::where('client_id', $subscription->client_id)
            ->where('week_start_date', $previousWeekStart)
            ->first();

        return $previousClientAssignment ? $previousClientAssignment->designer_id : null;
    }

    /**
     * يقوم بتعيين اشتراك لمصمم معين.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Designer  $designer
     * @param  \Carbon\Carbon  $weekStart
     * @return array
     */
    private function assignSubscriptionToDesigner(
        Subscription $subscription,
        Designer $designer,
        Carbon $weekStart
    ): array {
        $weekStartDate = $weekStart->format('Y-m-d');

        ClientDesigner::updateOrCreate(
            [
                'subscription_id' => $subscription->id,
                'week_start_date' => $weekStartDate,
            ],
            [
                'client_id' => $subscription->client_id,
                'designer_id' => $designer->id,
            ]
        );

        return [
            'success' => true,
            'score' => 0,
        ];
    }

    /**
     * يقوم بتنسيق النتائج النهائية لعملية التوزيع.
     *
     * @param  array  $results
     * @param  int  $totalSubscriptions
     * @return array
     */
    private function formatResults(array $results, int $totalSubscriptions): array
    {
        $distributedCount = count($results['distributed']);
        $failedCount = count($results['failed']);

        $message = sprintf(
            'تم توزيع %d من %d اشتراك بنجاح',
            $distributedCount,
            $totalSubscriptions
        );

        if ($failedCount > 0) {
            $message .= sprintf(' (%d فشل)', $failedCount);
        }

        return [
            'success' => $distributedCount > 0,
            'message' => $message,
            'distributed' => $distributedCount,
            'failed' => $failedCount,
            'total' => $totalSubscriptions,
            'details' => $results,
            'statistics' => $this->calculateStatistics($results),
        ];
    }

    /**
     * يحسب إحصائيات التوزيع للمصممين.
     *
     * @param  array  $results
     * @return array
     */
    private function calculateStatistics(array $results): array
    {
        $designerStats = [];

        foreach ($results['distributed'] as $item) {
            $designerId = $item['designer_id'];

            if (!isset($designerStats[$designerId])) {
                $designerStats[$designerId] = [
                    'designer_name' => $item['designer_name'],
                    'clients_count' => 0, // هنا نعني عدد الاشتراكات الموزعة
                    'total_designs' => 0,
                ];
            }

            $designerStats[$designerId]['clients_count']++;
            $designerStats[$designerId]['total_designs'] += $item['designs_count'];
        }

        return [
            'designers' => $designerStats,
            'total_designers_used' => count($designerStats),
        ];
    }

    /**
     * يقوم بجلب تقرير مفصل عن توزيعات أسبوع معين.
     *
     * @param  string  $weekStartDate تاريخ بداية الأسبوع (Y-m-d).
     * @return array تقرير التوزيع.
     */
    public function getDistributionReport(string $weekStartDate): array
    {
        $weekStart = Carbon::parse($weekStartDate)->startOfDay();
        $dateStr = $weekStart->format('Y-m-d');

        $assignments = ClientDesigner::with(['client', 'subscription', 'designer.user'])
            ->where('week_start_date', $dateStr)
            ->whereNotNull('subscription_id')
            ->get();

        // حساب الاشتراكات التي لم يتم توزيعها بعد
        $pendingCount = $this->getSubscriptionsNeedingDistribution($weekStart)->count();

        $report = [
            'week_start' => $dateStr,
            'total_assignments' => $assignments->count(),
            'pending_count' => $pendingCount,
            'designers' => [],
        ];

        foreach ($assignments as $assignment) {
            $designerId = $assignment->designer_id;

            if (!isset($report['designers'][$designerId])) {
                $report['designers'][$designerId] = [
                    'id' => $designerId,
                    'designer_name' => $assignment->designer->user->name ?? 'غير معروف',
                    'clients' => [],
                    'clients_count' => 0,
                    'total_designs' => 0,
                ];
            }

            $designsCount = $assignment->subscription?->designs_count ?? 0;
            $subType = ($assignment->subscription?->is_main ?? false) ? 'أساسي' : 'ثانوي';

            $report['designers'][$designerId]['clients'][] = [
                'id' => $assignment->client_id,
                'name' => ($assignment->client->client_name ?? $assignment->client->company) . " ({$subType})",
                'designs_count' => $designsCount,
            ];

            $report['designers'][$designerId]['clients_count']++;
            $report['designers'][$designerId]['total_designs'] += $designsCount;
        }

        return $report;
    }
}
