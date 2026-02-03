<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * يمثل هذا النموذج عميلاً في النظام.
 *
 * يحتوي على معلومات العميل الأساسية وعلاقاته مع النماذج الأخرى
 * مثل الموقع، العملة، الاحتياجات، الفئة، المصممين، والمستخدمين.
 *
 * @property int $id
 * @property string $company
 * @property string $client_name
 * @property int $location_id
 * @property string|null $address
 * @property string $contact_number
 * @property string|null $contact_job
 * @property string|null $marketing_amount
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property int|null $suspension_days
 * @property bool $is_credit_allowed
 * @property \Illuminate\Support\Carbon|null $suspended_at
 * @property int|null $category_id
 * @property string|null $customer_rating_value
 * @property int|null $change_cliche_threshold
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Client extends Model
{
    use HasFactory;

    /**
     * الأحداث التي يتم إطلاقها تلقائيًا عند حدوث تغييرات في النموذج.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'saved' => \App\Events\ClientChange::class,
        'deleted' => \App\Events\ClientChange::class,
    ];

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company',
        'client_name',
        'location_id',
        'address',
        'contact_number',
        'contact_job',
        'marketing_amount',
        'notified_at',
        'suspension_days',
        'is_credit_allowed',
        'suspended_at',
        'category_id',
        'customer_rating_value',
        'change_cliche_threshold',
        'added_by_user',
        'change_cliche_threshold',
        'added_by_user',
        'updated_by_user',
        'fixed_designer_id',
        'additional_designs_balance',
    ];

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المصمم المثبت (Fixed Designer).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function fixedDesigner(): BelongsTo
    {
        return $this->belongsTo(Designer::class, 'fixed_designer_id');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع الموقع (Location).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع العملة (Currency).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع احتياجات العميل (Client Needs).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function clientNeeds(): BelongsToMany
    {
        return $this->belongsToMany(ClientNeed::class, 'client_client_need');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع الفئة (Category).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع المصممين (Designers).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function designers(): BelongsToMany
    {
        return $this->belongsToMany(Designer::class, 'client_designer');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الوسوم (Tags).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'client_tag');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف العميل.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي قام بتحديث العميل.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user');
    }

    /**
     * يحدد علاقة "لديه العديد" (hasMany) مع الاشتراكات (Subscriptions).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * يحدد علاقة "لديه واحد" (hasOne) مع آخر اشتراك رئيسي (Latest Main Subscription).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function mainSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('is_main', true);
    }

    /**
     * يحدد علاقة "لديه العديد" (hasMany) مع المعاملات (Transactions).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * حساب الرصيد الحالي للعميل.
     *
     * @return float
     */
    public function getBalanceAttribute(): float
    {
        $credits = $this->transactions()->where('type', 'credit')->sum('amount');
        $debits = $this->transactions()->where('type', 'debit')->sum('amount');

        return $credits - $debits;
    }
}
