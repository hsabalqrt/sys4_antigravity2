<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * يمثل هذا النموذج وسمًا (tag) في النظام.
 *
 * تستخدم الوسوم لتصنيف وتنظيم المحتوى والكيانات الأخرى،
 * ويمكن أن تحتوي على معلومات حول التكرار والجدولة.
 *
 * @property int $id
 * @property string $name
 * @property string|null $importance
 * @property int|null $tag_group_id
 * @property bool $is_repetition
 * @property string|null $repetition
 * @property int|null $weekly_times
 * @property int|null $monthly_times
 * @property int|null $yearly_times
 * @property bool $is_there_date_for_sending
 * @property string|null $date_for_sending_yearly
 * @property string|null $weekly_day
 * @property string|null $weekly_time
 * @property string|null $weekly_time_sm
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Tag extends Model
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'weekly_day' => 'array',
        'is_repetition' => 'boolean',
        'is_there_date_for_sending' => 'boolean',
        'is_active' => 'boolean',
        'is_auto_assigned' => 'boolean',
    ];

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'importance',
        'tag_group_id',
        'is_repetition',
        'repetition',
        'weekly_times',
        'monthly_times',
        'yearly_times',
        'is_there_date_for_sending',
        'date_for_sending_yearly',
        'weekly_day',
        'weekly_time',
        'weekly_time_sm',
        'added_by_user',
        'updated_by_user',
        'is_active',
        'is_auto_assigned',
    ];

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الفئات (Categories).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_tag');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع العملاء (Clients).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_tag');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع المواقع (Locations).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_tag');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع مجموعة الوسوم (TagGroup).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tagGroup(): BelongsTo
    {
        return $this->belongsTo(TagGroup::class, 'tag_group_id');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف الوسم.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي قام بتحديث الوسم.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user');
    }
}
