<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * يمثل هذا النموذج احتياجات العميل.
 *
 * يحتوي على معلومات حول احتياجات العميل ومستوى أهميتها،
 * بالإضافة إلى علاقاتها مع الوسوم والفئات والعملاء والمستخدمين.
 *
 * @property int $id
 * @property string $name
 * @property array|null $importance_level
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ClientNeed extends Model
{
    /**
     * اسم الجدول المرتبط بالنموذج.
     *
     * @var string
     */
    protected $table = 'client_needs';

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'importance_level', // مستوى الأهمية
        'added_by_user',
        'updated_by_user',
    ];

    /**
     * السمات التي يجب تحويلها إلى أنواع بيانات أصلية.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'importance_level' => 'array',
    ];

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الوسوم (Tags).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'client_need_tags_group', 'client_need_id', 'tag_id');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الفئات (Categories).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_client_need');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع العملاء (Clients).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_client_need');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف الاحتياج.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي قام بتحديث الاحتياج.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user');
    }
}
