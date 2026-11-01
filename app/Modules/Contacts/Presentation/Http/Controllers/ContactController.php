<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Application\Actions\DeleteContact;
use App\Modules\Contacts\Application\Actions\GetContact;
use App\Modules\Contacts\Application\Actions\SearchContacts;
use App\Modules\Contacts\Application\Actions\UpsertContact;
use App\Modules\Contacts\Application\Assemblers\ContactDataFromRequest;
use App\Modules\Contacts\Application\Assemblers\SearchContactsDataFromRequest;
use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\ValueObjects\ContactId;
use App\Modules\Contacts\Presentation\Http\Resources\ContactResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\AbstractCursorPaginator;

final class ContactController extends Controller
{
    public function store(
        Request $request,
        ContactDataFromRequest $assembler,
        UpsertContact $action,
    ): JsonResponse {
        $id = ContactId::generate();
        $result = $action->handle($assembler->assemble($request, $id->value));

        return (new ContactResource($result->contact))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('contacts.show', $id->value));
    }

    public function update(
        Request $request,
        string $contact,
        ContactDataFromRequest $assembler,
        UpsertContact $action,
    ): JsonResponse {
        $result = $action->handle($assembler->assemble($request, $contact));

        return (new ContactResource($result->contact))
            ->response()
            ->setStatusCode($result->created ? 201 : 200);
    }

    public function show(
        string $contact,
        GetContact $action,
    ): ContactResource {
        return new ContactResource($action->handle($contact));
    }

    public function index(
        Request $request,
        SearchContactsDataFromRequest $assembler,
        SearchContacts $action,
    ): AnonymousResourceCollection {
        /** @var CursorPaginator<int, Contact>&AbstractCursorPaginator<int, Contact> $page */
        $page = $action->handle($assembler->assemble($request));

        return ContactResource::collection($page->withQueryString());
    }

    public function destroy(
        string $contact,
        DeleteContact $action,
    ): Response {
        $action->handle($contact);

        return response()->noContent();
    }
}
