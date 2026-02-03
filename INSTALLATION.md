# دليل التثبيت والإعداد - نظام التوزيع الذكي

## 📋 المتطلبات الأساسية

- ✅ PHP >= 8.1
- ✅ Laravel >= 10.x
- ✅ Filament >= 3.x
- ✅ MySQL/PostgreSQL
- ✅ Composer

---

## 🚀 خطوات التثبيت

### 1. التحقق من الملفات

تأكد من وجود جميع الملفات التالية:

```
✅ app/Services/DesignerDistributionService.php
✅ app/Filament/Pages/DesignerDistribution.php
✅ app/Console/Commands/AutoDistributeDesigners.php
✅ resources/views/filament/pages/designer-distribution.blade.php
✅ config/designer_distribution.php
✅ tests/Feature/DesignerDistributionTest.php
```

### 2. تحديث Composer (إذا لزم الأمر)

```bash
composer dump-autoload
```

### 3. نشر ملف التكوين

```bash
# إذا كنت تريد نشر ملف التكوين بشكل منفصل
php artisan vendor:publish --tag=config
```

### 4. إضافة متغيرات البيئة

أضف المتغيرات التالية إلى ملف `.env`:

```env
# أوزان نظام النقاط
DISTRIBUTION_WEIGHT_SPECIALIZATION=40
DISTRIBUTION_WEIGHT_RATING=25
DISTRIBUTION_WEIGHT_CONTINUITY=20
DISTRIBUTION_WEIGHT_EXPERIENCE=10
DISTRIBUTION_WEIGHT_CAPACITY_BALANCE=5

# إعدادات أساسية
DISTRIBUTION_CHECK_TOTAL_DESIGNS=false
DISTRIBUTION_CONTINUITY_ENABLED=true
DISTRIBUTION_LOG_ENABLED=true
```

أو انسخ من الملف المثال:
```bash
cat .env.distribution.example >> .env
```

### 5. مسح الـ Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### 6. التحقق من قاعدة البيانات

تأكد من وجود الجداول التالية:

```sql
-- التحقق من الجداول
SHOW TABLES LIKE 'designers';
SHOW TABLES LIKE 'clients';
SHOW TABLES LIKE 'client_designer';
SHOW TABLES LIKE 'categories';
SHOW TABLES LIKE 'subscriptions';
```

إذا كان جدول `client_designer` غير موجود، قم بإنشائه:

```sql
CREATE TABLE client_designer (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    designer_id BIGINT UNSIGNED NOT NULL,
    week_start_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (designer_id) REFERENCES designers(id) ON DELETE CASCADE,
    INDEX idx_week_start (week_start_date),
    INDEX idx_client (client_id),
    INDEX idx_designer (designer_id)
);
```

أو استخدم Migration:

```bash
php artisan make:migration create_client_designer_table
```

ثم أضف في ملف Migration:

```php
public function up()
{
    Schema::create('client_designer', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->onDelete('cascade');
        $table->foreignId('designer_id')->constrained()->onDelete('cascade');
        $table->date('week_start_date');
        $table->timestamps();
        
        $table->index('week_start_date');
        $table->index(['client_id', 'week_start_date']);
        $table->index(['designer_id', 'week_start_date']);
    });
}
```

```bash
php artisan migrate
```

---

## 🔧 الإعداد الأولي

### 1. إعداد بيانات المصممين

تأكد من أن جميع المصممين لديهم:

```sql
-- تحديث السعة للمصممين
UPDATE designers 
SET max_capacity = 10, 
    min_capacity = 5,
    rate = 8
WHERE max_capacity IS NULL;
```

### 2. إعداد بيانات العملاء

تأكد من أن جميع العملاء لديهم اشتراك رئيسي:

```sql
-- التحقق من العملاء بدون اشتراك
SELECT c.id, c.company 
FROM clients c
LEFT JOIN subscriptions s ON c.id = s.client_id AND s.is_main = 1
WHERE s.id IS NULL;

-- إنشاء اشتراكات للعملاء بدون اشتراك
INSERT INTO subscriptions (client_id, is_main, designs_count, start_date, subscription_type, payment_amount, payment_type, created_at, updated_at)
SELECT 
    c.id,
    1,
    10,
    NOW(),
    'monthly',
    1000,
    'advance',
    NOW(),
    NOW()
FROM clients c
LEFT JOIN subscriptions s ON c.id = s.client_id AND s.is_main = 1
WHERE s.id IS NULL;
```

### 3. إعداد التصنيفات

ربط المصممين بالتصنيفات:

```sql
-- التحقق من المصممين بدون تصنيفات
SELECT d.id, u.name
FROM designers d
JOIN users u ON d.user_id = u.id
LEFT JOIN category_designer cd ON d.id = cd.designer_id
WHERE cd.designer_id IS NULL;
```

---

## ✅ التحقق من التثبيت

### 1. اختبار Service Class

```bash
php artisan tinker
```

```php
$service = new App\Services\DesignerDistributionService();
$result = $service->autoDistribute(now()->startOfWeek()->format('Y-m-d'));
dd($result);
```

### 2. اختبار Artisan Command

```bash
php artisan designers:auto-distribute --help
php artisan designers:auto-distribute
```

### 3. اختبار Filament Page

1. افتح المتصفح
2. انتقل إلى لوحة التحكم
3. ابحث عن "توزيع المصممين" في القائمة
4. إذا لم تظهر، قم بمسح الـ Cache:

```bash
php artisan filament:cache-components
php artisan optimize:clear
```

### 4. تشغيل الاختبارات

```bash
# اختبار واحد للتأكد
php artisan test --filter it_can_distribute_clients_to_designers

# جميع الاختبارات
php artisan test --filter DesignerDistributionTest
```

---

## 🎨 تخصيص واجهة Filament

### إضافة الصفحة إلى القائمة

إذا لم تظهر الصفحة في القائمة، تحقق من:

```php
// في app/Filament/Pages/DesignerDistribution.php
protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
protected static ?string $navigationGroup = 'إدارة التوزيع';
protected static ?string $navigationLabel = 'توزيع المصممين';
```

### تغيير الأيقونة

```php
protected static ?string $navigationIcon = 'heroicon-o-users'; // أو أي أيقونة أخرى
```

### تغيير الترتيب

```php
protected static ?int $navigationSort = 1;
```

---

## 🔐 الصلاحيات (اختياري)

إذا كنت تريد تقييد الوصول للصفحة:

```php
// في app/Filament/Pages/DesignerDistribution.php
public static function canAccess(): bool
{
    return auth()->user()->hasRole('admin');
}
```

---

## 📊 إعداد الجدولة التلقائية

### 1. تحديث Kernel

في `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // توزيع تلقائي كل يوم أحد الساعة 12 صباحاً
    $schedule->command('designers:auto-distribute --clear')
        ->weeklyOn(0, '00:00')
        ->timezone('Asia/Riyadh')
        ->emailOutputOnFailure('admin@example.com');
}
```

### 2. تفعيل Cron Job

على الخادم، أضف:

```bash
crontab -e
```

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### 3. اختبار الجدولة

```bash
# تشغيل يدوي
php artisan schedule:run

# عرض الجدولة
php artisan schedule:list
```

---

## 🐛 استكشاف مشاكل التثبيت

### المشكلة: الصفحة لا تظهر في Filament

**الحل:**
```bash
php artisan filament:cache-components
php artisan optimize:clear
php artisan config:clear
```

### المشكلة: خطأ "Class not found"

**الحل:**
```bash
composer dump-autoload
php artisan optimize:clear
```

### المشكلة: خطأ في قاعدة البيانات

**الحل:**
```bash
# التحقق من الاتصال
php artisan db:show

# تشغيل Migrations
php artisan migrate

# التحقق من الجداول
php artisan tinker
>>> DB::table('designers')->count()
```

### المشكلة: خطأ في الـ View

**الحل:**
```bash
php artisan view:clear
php artisan view:cache
```

---

## 📝 بيانات تجريبية (اختياري)

لإنشاء بيانات تجريبية للاختبار:

```bash
php artisan tinker
```

```php
// إنشاء مصممين
$user1 = User::create(['name' => 'مصمم 1', 'email' => 'designer1@test.com', 'password' => bcrypt('password')]);
$designer1 = Designer::create([
    'user_id' => $user1->id,
    'min_capacity' => 5,
    'max_capacity' => 10,
    'rate' => 8,
    'shift_hours' => 8,
    'amount_of_designs' => 100,
]);

// إنشاء عملاء
$client1 = Client::create([
    'company' => 'شركة اختبار',
    'client_name' => 'عميل تجريبي',
]);

// إنشاء اشتراك
Subscription::create([
    'client_id' => $client1->id,
    'is_main' => true,
    'designs_count' => 15,
    'start_date' => now(),
    'subscription_type' => 'monthly',
    'payment_amount' => 1000,
    'payment_type' => 'advance',
]);
```

---

## ✅ قائمة التحقق النهائية

- [ ] جميع الملفات موجودة
- [ ] Composer autoload محدّث
- [ ] متغيرات .env مضافة
- [ ] Cache ممسوح
- [ ] قاعدة البيانات جاهزة
- [ ] جدول client_designer موجود
- [ ] المصممين لديهم max_capacity
- [ ] العملاء لديهم اشتراكات رئيسية
- [ ] Service Class يعمل
- [ ] Artisan Command يعمل
- [ ] Filament Page تظهر
- [ ] الاختبارات تنجح

---

## 🎉 الخطوات التالية

بعد التثبيت الناجح:

1. ✅ جرّب التوزيع التلقائي
2. ✅ راجع التقارير
3. ✅ خصص الأوزان حسب احتياجك
4. ✅ فعّل الجدولة التلقائية
5. ✅ راجع التوثيق الكامل

---

## 📞 الدعم

إذا واجهت أي مشاكل:

1. راجع [التوثيق الكامل](docs/DESIGNER_DISTRIBUTION_ALGORITHM.md)
2. راجع [دليل البدء السريع](docs/DISTRIBUTION_QUICK_START.md)
3. تحقق من السجلات: `storage/logs/laravel.log`
4. تواصل مع فريق التطوير

---

**تم بنجاح! 🎉**

الآن يمكنك البدء في استخدام نظام التوزيع الذكي.
