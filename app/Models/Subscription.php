<?php

namespace App\Models;

use App\Filament\Enums\SubscriptionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * يمثل هذا النموذج اشتراكًا في النظام.
 *
 * يحتوي على معلومات حول اشتراك العميل، مثل عدد التصاميم، تاريخ البدء،
 * ونوع الاشتراك، بالإضافة إلى علاقاته مع العميل، العملة، والوسوم.
 *
 * @property int $id
 * @property int $client_id
 * @property bool $is_main
 * @property int $designs_count
 * @property \Illuminate\Support\Carbon $start_date
 * @property string $subscription_type
 * @property float $payment_amount
 * @property int $currency_id
 * @property string $payment_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Subscription extends Model {
    use HasFactory;

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'is_main',
        'designs_count',
        'start_date',
        'end_date',
        'subscription_type',
        'payment_amount',
        'currency_id',
        'payment_type',
        'status',
    ];

    /**
     * السمات التي يجب تحويلها إلى أنواع بيانات أصلية.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_main' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'designs_count' => 'integer',
        'payment_amount' => 'decimal:2',
        'subscription_type' => SubscriptionType::class,
        'payment_type' => 'string',
    ];

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع العميل (Client).
     */
    public function client(): BelongsTo {
        return $this->belongsTo(Client::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع العملة (Currency).
     */
    public function currency(): BelongsTo {
        return $this->belongsTo(Currency::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الوسوم (Tags).
     */
    public function tags(): BelongsToMany {
        return $this->belongsToMany(Tag::class, 'subscription_tags');
    }
    /**
     * حساب تاريخ الانتهاء بناءً على تاريخ البدء ونوع الاشتراك.
     */
    /**
     * حساب تاريخ الانتهاء (في حال عدم وجوده كعمود، نحتفظ بالمنطق احتياطياً أو نستخدمه لضبط القيمة الافتراضية).
     * ولكن بما أننا أضفنا عمود end_date، سنستخدمه مباشرة.
     */
    // public function getEndDateAttribute() ... REMOVED to use native column.

    /**
     * حساب الأيام المتبقية.
     */
    public function getDaysRemainingAttribute() {
        if (! $this->end_date) {
            return 30;
        } // Default safe margin if not calculated

        return now()->startOfDay()->diffInDays($this->end_date->startOfDay(), false);
    }

    /**
     * حساب الحالة بناءً على التواريخ (للاستخدام الداخلي أو التحديث).
     */
    public function calculateStatus(): string {
        $days = $this->days_remaining;

        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 2) {
            return 'expiring_soon';
        }

        return 'active';
    }

    /**
     * تشغيل العمليات عند حفظ النموذج.
     */
    protected static function booted() {
        static::saving(function ($subscription) {
            // تلقائياً حساب تاريخ الانتهاء إذا تم تعديل تاريخ البدء أو النوع (ولم يتم تعديل تاريخ الانتهاء يدوياً)، أو إذا كان مفقوداً
            $shouldUpdateEndDate = (! $subscription->end_date) ||
                ($subscription->isDirty(['start_date', 'subscription_type']) && ! $subscription->isDirty('end_date'));

            if ($shouldUpdateEndDate) {
                $subscription->end_date = static::calculateEndDateFrom($subscription->start_date, $subscription->subscription_type);
            }
        });

        static::created(function ($subscription) {
            // تسجيل الرسوم دائماً
            $subscription->generateDebitTransaction();
        });
    }

    /**
     * حساب تاريخ الانتهاء بناءً على تاريخ البدء ونوع الاشتراك.
     */
    public static function calculateEndDateFrom($startDate = null, $type = 'monthly'): \Illuminate\Support\Carbon {
        $start = $startDate ? \Illuminate\Support\Carbon::parse($startDate) : now();

        return match ($type) {
            'weekly' => $start->copy()->addWeek(),
            'yearly' => $start->copy()->addYear(),
            default => $start->copy()->addMonth(), // monthly is default
        };
    }

    /**
     * إضافة رسوم اشتراك تلقائياً في كشف الحساب.
     */
    public function generateDebitTransaction() {
        // إذا كان الدفع آجلاً (deferred)، لا يتم إنشاء قيد استحقاق عند الإنشاء (يتم عبر وظيفة يومية)
        if ($this->payment_type === 'deferred') {
            return null;
        }

        if ($this->payment_amount <= 0) {
            return null;
        }

        return Transaction::create([
            'client_id' => $this->client_id,
            'subscription_id' => $this->id,
            'amount' => $this->payment_amount,
            'original_amount' => $this->payment_amount,
            'currency_id' => $this->currency_id,
            'exchange_rate' => 1.0,
            'type' => 'debit',
            'description' => 'رسوم اشتراك: ' . ($this->subscription_type === 'monthly' ? 'شهري' : ($this->subscription_type === 'yearly' ? 'سنوي' : 'مخصص')),
            'transaction_date' => $this->start_date ?? now(),
        ]);
    }

    /**
     * إضافة سداد تلقائي في كشف الحساب.
     */
    public function generateCreditTransaction() {
        return Transaction::create([
            'client_id' => $this->client_id,
            'subscription_id' => $this->id,
            'amount' => $this->payment_amount,
            'original_amount' => $this->payment_amount,
            'currency_id' => $this->currency_id,
            'exchange_rate' => 1.0,
            'type' => 'credit',
            'description' => 'سداد مقدم للاشتراك: ' . ($this->subscription_type === 'monthly' ? 'شهري' : ($this->subscription_type === 'yearly' ? 'سنوي' : 'مخصص')),
            'transaction_date' => now(),
        ]);
    }

    /**
     * يمثل علاقة "لديه العديد" (hasMany) مع المعاملات (Transactions).
     */
    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(Transaction::class);
    }

    /**
     * حساب رصيد الاشتراك.
     */
    public function getBalanceAttribute(): float {
        $credits = (float) $this->transactions()->where('type', 'credit')->sum('amount');
        $debits = (float) $this->transactions()->where('type', 'debit')->sum('amount');

        // Round to 2 decimals to avoid tiny floating errors
        return round($credits - $debits, 2);
    }

    /**
     * الحصول على حالة السداد.
     */
    public function getPaymentStatusAttribute(): string {
        $paid = $this->transactions()->where('type', 'credit')->sum('amount');

        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid >= $this->payment_amount) {
            return 'paid';
        }

        return 'partial';
    }
}
