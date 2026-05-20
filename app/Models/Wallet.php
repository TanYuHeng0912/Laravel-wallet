<?php

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'balance'])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    /**
     * Always expose money as a two-decimal string in arrays / JSON.
     *
     * Laravel may hydrate DECIMAL columns as strings already, but normalizing
     * here makes API responses consistent across MySQL and SQLite tests.
     */
    protected function balance(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): string => $this->formatMoney($value),
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    private function formatMoney(mixed $value): string
    {
        $value = (string) $value;
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '00');

        return $whole.'.'.str_pad(substr($decimal, 0, 2), 2, '0');
    }
}
