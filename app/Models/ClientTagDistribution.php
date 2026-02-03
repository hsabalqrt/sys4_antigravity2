<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTagDistribution extends Model
{
    protected $fillable = [
        'client_designer_id',
        'tag_id',
        'distribution_date',
        'scheduled_sending_at',
        'idea_id',
        'custom_idea',
        'status',
        'designer_notes',
        'attachment_path',
        'reviewer_feedback',
        'reviewer_id',
        'sender_id',
    ];

    protected $casts = [
        'scheduled_sending_at' => 'datetime',
    ];

    public function clientDesigner(): BelongsTo
    {
        return $this->belongsTo(ClientDesigner::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
