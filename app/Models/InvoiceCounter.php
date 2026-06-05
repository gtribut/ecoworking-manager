<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvoiceCounterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Compteur de numérotation chronologique sans trou, verrouillé en transaction
 * (SELECT … FOR UPDATE). Un compteur par année. data_model §4.4 / §6.5.
 */
#[Fillable(['year', 'value'])]
class InvoiceCounter extends Model
{
    /** @use HasFactory<InvoiceCounterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'value' => 'integer',
        ];
    }
}
