<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\Models;

use Database\Factories\Contacts\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ContactFactory::class)]
final class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['id', 'name'];

    /**
     * @return HasMany<ContactEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(ContactEmail::class)->orderBy('id');
    }

    /**
     * @return HasMany<ContactPhone, $this>
     */
    public function phones(): HasMany
    {
        return $this->hasMany(ContactPhone::class)->orderBy('id');
    }
}
