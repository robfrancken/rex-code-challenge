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
use App\Modules\Contacts\Presentation\Http\Resources\ContactResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class ContactController extends Controller
{
    public function upsert(
        Request $request,
        string $id,
        ContactDataFromRequest $assembler,
        UpsertContact $action,
    ): JsonResponse {
        $result = $action->handle($assembler->assemble($request, $id));

        return (new ContactResource($result->contact))
            ->response()
            ->setStatusCode($result->created ? 201 : 200);
    }

    public function show(
        string $id,
        GetContact $action,
    ): ContactResource {
        return new ContactResource($action->handle($id));
    }

    public function index(
        Request $request,
        SearchContactsDataFromRequest $assembler,
        SearchContacts $action,
    ): AnonymousResourceCollection {
        return ContactResource::collection($action->handle($assembler->assemble($request)));
    }

    public function destroy(
        string $id,
        DeleteContact $action,
    ): Response {
        $action->handle($id);

        return response()->noContent();
    }
}
