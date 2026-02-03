<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * يمثل هذا النموذج مجموعة وسوم في النظام.
 *
 * يستخدم لتجميع وتنظيم الوسوم ذات الصلة.
 *
 * @property int $id
 * @property string $name
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TagGroup extends Model
{
    use HasFactory;

    /**
     * اسم الجدول المرتبط بالنموذج.
     *
     * @var string
     */
    protected $table = 'tags_groups';

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'added_by_user',
        'updated_by_user',
    ];

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف مجموعة الوسوم.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي قام بتحديث مجموعة الوسوم.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user');
    }

    /**
     * يحدد علاقة "لديه العديد" (hasMany) مع الوسوم (Tags).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع احتياجات العميل (ClientNeeds).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function clientNeeds(): BelongsToMany
    {
        return $this->belongsToMany(ClientNeed::class, 'client_need_tags_group');
    }
}
