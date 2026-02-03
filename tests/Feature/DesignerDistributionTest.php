<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DesignerDistributionService;
use App\Models\Designer;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\Category;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class DesignerDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected DesignerDistributionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DesignerDistributionService();
    }

    /** @test */
    public function it_can_distribute_clients_to_designers()
    {
        // إعداد البيانات
        $category = Category::factory()->create();
        
        $designer = $this->createDesigner([
            'min_capacity' => 5,
            'max_capacity' => 10,
            'rate' => 8,
        ], [$category->id]);

        $client = $this->createClient([
            'category_id' => $category->id,
        ], 15); // 15 تصميم

        // التوزيع
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // التحقق
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['distributed']);
        $this->assertEquals(0, $result['failed']);

        $this->assertDatabaseHas('client_designer', [
            'client_id' => $client->id,
            'designer_id' => $designer->id,
            'week_start_date' => $weekStart,
        ]);
    }

    /** @test */
    public function it_prioritizes_specialized_designers()
    {
        $category1 = Category::factory()->create(['name' => 'تصميم']);
        $category2 = Category::factory()->create(['name' => 'برمجة']);

        // مصمم متخصص في التصميم
        $specializedDesigner = $this->createDesigner([
            'rate' => 7,
            'max_capacity' => 10,
        ], [$category1->id]);

        // مصمم غير متخصص لكن تقييمه أعلى
        $generalDesigner = $this->createDesigner([
            'rate' => 9,
            'max_capacity' => 10,
        ], [$category2->id]);

        // عميل في تصنيف التصميم
        $client = $this->createClient([
            'category_id' => $category1->id,
        ], 10);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // يجب أن يتم اختيار المصمم المتخصص رغم أن تقييمه أقل
        $this->assertDatabaseHas('client_designer', [
            'client_id' => $client->id,
            'designer_id' => $specializedDesigner->id,
        ]);
    }

    /** @test */
    public function it_respects_designer_capacity()
    {
        $category = Category::factory()->create();
        
        $designer = $this->createDesigner([
            'max_capacity' => 2,
            'rate' => 8,
        ], [$category->id]);

        // إنشاء 3 عملاء
        $clients = [];
        for ($i = 0; $i < 3; $i++) {
            $clients[] = $this->createClient([
                'category_id' => $category->id,
            ], 10);
        }

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // يجب توزيع 2 فقط (السعة القصوى)
        $this->assertEquals(2, $result['distributed']);
        $this->assertEquals(1, $result['failed']);
    }

    /** @test */
    public function it_maintains_continuity_with_previous_week()
    {
        $category = Category::factory()->create();
        
        $designer1 = $this->createDesigner([
            'rate' => 7,
            'max_capacity' => 10,
        ], [$category->id]);

        $designer2 = $this->createDesigner([
            'rate' => 9, // تقييم أعلى
            'max_capacity' => 10,
        ], [$category->id]);

        $client = $this->createClient([
            'category_id' => $category->id,
        ], 10);

        // تعيين سابق في الأسبوع الماضي
        $previousWeek = Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d');
        ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer1->id,
            'week_start_date' => $previousWeek,
        ]);

        // التوزيع للأسبوع الحالي
        $currentWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($currentWeek);

        // يجب اختيار نفس المصمم (الاستمرارية) رغم أن المصمم الآخر تقييمه أعلى
        $this->assertDatabaseHas('client_designer', [
            'client_id' => $client->id,
            'designer_id' => $designer1->id,
            'week_start_date' => $currentWeek,
        ]);
    }

    /** @test */
    public function it_balances_load_between_designers()
    {
        $category = Category::factory()->create();
        
        $designer1 = $this->createDesigner([
            'min_capacity' => 3,
            'max_capacity' => 10,
            'rate' => 8,
        ], [$category->id]);

        $designer2 = $this->createDesigner([
            'min_capacity' => 3,
            'max_capacity' => 10,
            'rate' => 8,
        ], [$category->id]);

        // إنشاء 6 عملاء
        for ($i = 0; $i < 6; $i++) {
            $this->createClient([
                'category_id' => $category->id,
            ], 10);
        }

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // التحقق من التوزيع المتوازن
        $designer1Load = ClientDesigner::where('designer_id', $designer1->id)
            ->where('week_start_date', $weekStart)
            ->count();

        $designer2Load = ClientDesigner::where('designer_id', $designer2->id)
            ->where('week_start_date', $weekStart)
            ->count();

        // يجب أن يكون التوزيع متوازن (3 لكل مصمم)
        $this->assertEquals(3, $designer1Load);
        $this->assertEquals(3, $designer2Load);
    }

    /** @test */
    public function it_handles_clients_without_category()
    {
        $designer = $this->createDesigner([
            'max_capacity' => 10,
            'rate' => 8,
        ], []);

        $client = $this->createClient([
            'category_id' => null, // بدون تصنيف
        ], 10);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // يجب أن يتم التوزيع بنجاح
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['distributed']);
    }

    /** @test */
    public function it_returns_error_when_no_designers_available()
    {
        $client = $this->createClient([], 10);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('لا يوجد مصممين متاحين', $result['message']);
    }

    /** @test */
    public function it_generates_distribution_report()
    {
        $category = Category::factory()->create();
        
        $designer = $this->createDesigner([
            'max_capacity' => 10,
            'rate' => 8,
        ], [$category->id]);

        $client = $this->createClient([
            'category_id' => $category->id,
        ], 15);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->service->autoDistribute($weekStart);

        // الحصول على التقرير
        $report = $this->service->getDistributionReport($weekStart);

        $this->assertEquals($weekStart, $report['week_start']);
        $this->assertEquals(1, $report['total_assignments']);
        $this->assertArrayHasKey('designers', $report);
        $this->assertCount(1, $report['designers']);
    }

    /** @test */
    public function it_assigns_experienced_designers_to_large_clients()
    {
        $category = Category::factory()->create();
        
        // مصمم خبير
        $experiencedDesigner = $this->createDesigner([
            'amount_of_designs' => 500,
            'rate' => 7,
            'max_capacity' => 10,
        ], [$category->id]);

        // مصمم مبتدئ
        $juniorDesigner = $this->createDesigner([
            'amount_of_designs' => 50,
            'rate' => 7,
            'max_capacity' => 10,
        ], [$category->id]);

        // عميل كبير (أكثر من 20 تصميم)
        $largeClient = $this->createClient([
            'category_id' => $category->id,
        ], 30);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // يجب اختيار المصمم الخبير للعميل الكبير
        $this->assertDatabaseHas('client_designer', [
            'client_id' => $largeClient->id,
            'designer_id' => $experiencedDesigner->id,
        ]);
    }

    // Helper Methods

    protected function createDesigner(array $attributes = [], array $categoryIds = []): Designer
    {
        $user = User::factory()->create();
        
        $designer = Designer::create(array_merge([
            'user_id' => $user->id,
            'min_capacity' => 5,
            'max_capacity' => 10,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ], $attributes));

        if (!empty($categoryIds)) {
            $designer->categories()->attach($categoryIds);
        }

        return $designer->fresh(['categories', 'user']);
    }

    protected function createClient(array $attributes = [], int $designsCount = 10): Client
    {
        $client = Client::factory()->create($attributes);

        // إنشاء اشتراك رئيسي
        Subscription::create([
            'client_id' => $client->id,
            'is_main' => true,
            'designs_count' => $designsCount,
            'start_date' => Carbon::now(),
            'subscription_type' => 'monthly',
            'payment_amount' => 1000,
            'payment_type' => 'advance',
        ]);

        return $client->fresh(['mainSubscription', 'category']);
    }
}
