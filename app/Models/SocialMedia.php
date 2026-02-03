<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * يمثل هذا النموذج وسيلة تواصل اجتماعي في النظام.
 *
 * يحتوي على معلومات حول وسيلة التواصل الاجتماعي، بالإضافة إلى
 * علاقاتها مع المستخدمين الذين قاموا بإضافتها وتحديثها.
 *
 * @property int $id
 * @property string $name
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SocialMedia extends Model
{
    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'added_by_user', 'updated_by_user'];

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف وسيلة التواصل.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي قام بتحديث وسيلة التواصل.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user');
    }
}
