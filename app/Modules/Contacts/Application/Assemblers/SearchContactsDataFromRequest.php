<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Assemblers;

use App\Modules\Contacts\Application\DTOs\SearchContactsData;
use App\Modules\Contacts\Application\Validation\SearchContactsValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class SearchContactsDataFromRequest
{
    public function __construct(private SearchContactsValidator $validator) {}

    /**
     * @throws ValidationException
     */
    public function assemble(Request $request): SearchContactsData
    {
        return SearchContactsData::fromValidated($this->validator->validate(
            SearchContactsValidator::normalise($request->only('name', 'phone', 'email_domain', 'page', 'per_page')),
        ));
    }
}
