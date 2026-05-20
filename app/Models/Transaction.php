<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wallet_id', 'type', 'amount'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): string => $this->formatMoney($value),
        );
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    private function formatMoney(mixed $value): string
    {
        $value = (string) $value;
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '00');

        return $whole.'.'.str_pad(substr($decimal, 0, 2), 2, '0');
    }
}
