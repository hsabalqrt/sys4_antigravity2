<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * يمثل هذا النموذج مصممًا في النظام.
 *
 * يحتوي على معلومات حول المصمم، مثل القدرة الإنتاجية، التقييم،
 * وساعات العمل، بالإضافة إلى علاقاته مع المستخدمين، الفئات، والعملاء.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $min_capacity
 * @property int|null $max_capacity
 * @property float|null $rate
 * @property int|null $shift_hours
 * @property float|null $discipline_score
 * @property int|null $amount_of_designs
 * @property string|null $freepik_account
 * @property string|null $pc_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Designer extends Model
{
    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'min_capacity',
        'max_capacity',
        'rate',
        'shift_hours',
        'discipline_score',
        'amount_of_designs',
        'freepik_account',
        'pc_number',
    ];

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم (User).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الفئات (Categories).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع العملاء (Clients).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_designer');
    }

    /**
     * يحدد علاقة "لديه العديد" (hasMany) مع `ClientDesigner`.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function clientDesigners(): HasMany
    {
        return $this->hasMany(ClientDesigner::class, 'designer_id');
    }

    /**
     * يحسب مجموع حدود التصميم لجميع العملاء المرتبطين بهذا المصمم.
     *
     * @return float|int
     */
    public function clientsDesignLimitSum()
    {
        // مجموع design_limit لكل العملاء المرتبطين بالمصمم
        return $this->clientDesigners()->with('client.package')->get()
            ->sum(fn($clientDesigner) => optional($clientDesigner->client->package)->design_limit ?? 0);
    }
}
