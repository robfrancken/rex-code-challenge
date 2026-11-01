<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\Models;

use Database\Factories\Contacts\ContactPhoneFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ContactPhoneFactory::class)]
final class ContactPhone extends Model
{
    /** @use HasFactory<ContactPhoneFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['contact_id', 'phone'];

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
