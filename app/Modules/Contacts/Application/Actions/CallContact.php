<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\DTOs\CallContactData;
use App\Modules\Contacts\Application\DTOs\CallResult;
use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Application\Exceptions\TelephonyFailed;
use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Domain\Telephony\TelephonyGateway;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class CallContact
{
    public function __construct(private TelephonyGateway $gateway) {}

    /**
     * @throws ContactNotFound
     * @throws ValidationException
     * @throws TelephonyFailed
     */
    public function handle(CallContactData $data): CallResult
    {
        $contact = Contact::query()->with('phones')->find($data->contactId)
            ?? throw ContactNotFound::withId($data->contactId);

        $number = $this->chooseNumber($contact, $data->phone);
        $outcome = $this->gateway->place($number);

        if ($outcome === CallOutcome::Failed) {
            throw TelephonyFailed::forPhone($number);
        }

        return new CallResult($contact->id, $number, $outcome, CarbonImmutable::now());
    }

    /**
     * @throws ValidationException
     */
    private function chooseNumber(Contact $contact, ?PhoneNumber $requested): PhoneNumber
    {
        $owned = $contact->phones->map(fn (ContactPhone $phone): PhoneNumber => PhoneNumber::fromString($phone->phone));

        if ($requested === null) {
            return $owned->first() ?? throw ValidationException::withMessages([
                'phone' => 'The contact has no phone numbers.',
            ]);
        }

        if ($owned->doesntContain(fn (PhoneNumber $phone): bool => $phone->equals($requested))) {
            throw ValidationException::withMessages([
                'phone' => "The phone must be one of this contact's phone numbers.",
            ]);
        }

        return $requested;
    }
}
