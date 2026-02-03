<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * يمثل هذا النموذج معاملة مالية في النظام.
 *
 * يتتبع المدفوعات (دائن) والرسوم (مدين) للعملاء.
 *
 * @property int $id
 * @property int $client_id
 * @property float $amount
 * @property string $type
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $transaction_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'subscription_id',
        'currency_id',
        'amount',
        'original_amount',
        'exchange_rate',
        'type',
        'description',
        'transaction_date',
        'payment_gateway',
        'transfer_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'transaction_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
