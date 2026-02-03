<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * يمثل هذا النموذج فئة أو تصنيفًا في النظام.
 *
 * يمكن ربط الفئات بالمصممين والوسوم.
 *
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Category extends Model
{
    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع المصممين (Designers).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function designers(): BelongsToMany
    {
        return $this->belongsToMany(Designer::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الوسوم (Tags).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'category_tag');
    }
}
