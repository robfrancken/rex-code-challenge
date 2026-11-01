<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Assemblers;

use App\Modules\Contacts\Application\DTOs\SearchContactsData;
use App\Modules\Contacts\Application\Validation\SearchContactsValidator;
use Illuminate\Validation\ValidationException;

final readonly class SearchContactsDataFromCli
{
    public function __construct(private SearchContactsValidator $validator) {}

    /**
     * @param  array<string, mixed>  $options  keys: name, phone, domain, page, per-page
     *
     * @throws ValidationException
     */
    public function assemble(array $options): SearchContactsData
    {
        return SearchContactsData::fromValidated($this->validator->validate(
            SearchContactsValidator::normalise([
                'name' => $options['name'] ?? null,
                'phone' => $options['phone'] ?? null,
                'email_domain' => $options['domain'] ?? null,
                'page' => $options['page'] ?? null,
                'per_page' => $options['per-page'] ?? null,
            ]),
        ));
    }
}
