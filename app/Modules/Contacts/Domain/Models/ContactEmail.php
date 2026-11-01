<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\Models;

use Database\Factories\Contacts\ContactEmailFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ContactEmailFactory::class)]
final class ContactEmail extends Model
{
    /** @use HasFactory<ContactEmailFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['contact_id', 'email', 'domain'];

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
