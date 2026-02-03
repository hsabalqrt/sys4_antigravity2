<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * يمثل هذا النموذج فكرة في النظام.
 *
 * يحتوي على معلومات حول الفكرة، مثل المحتوى والوصف، بالإضافة إلى
 * علاقاتها مع المستخدمين، العملاء، المواقع، والوسوم.
 *
 * @property int $id
 * @property string $name
 * @property string|null $content
 * @property string|null $description
 * @property bool $repeat_for_clients
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property string|null $idea_file
 * @property bool $is_visible_in_generator
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Idea extends Model
{
    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'content',
        'description',
        'repeat_for_clients',
        'scheduled_at',
        'idea_file',
        'is_visible_in_generator',
        'added_by_user',
        'updated_by_user',
    ];

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف الفكرة.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي قام بتحديث الفكرة.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع العملاء (Clients).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_idea', 'idea_id', 'client_id');
    }

    /**
     * يحدد علاقة "لديه العديد" (hasMany) مع العملاء المحظورين من هذه الفكرة.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function blockedClients(): HasMany
    {
        return $this->hasMany(IdeaClientBlock::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع المواقع (Locations).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_idea');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الوسوم (Tags).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_idea');
    }
}
