<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Application\Actions\CallContact;
use App\Modules\Contacts\Application\Assemblers\CallContactDataFromRequest;
use App\Modules\Contacts\Presentation\Http\Resources\CallResource;
use Illuminate\Http\Request;

final class CallController extends Controller
{
    public function store(
        Request $request,
        string $contact,
        CallContactDataFromRequest $assembler,
        CallContact $action,
    ): CallResource {
        return new CallResource($action->handle($assembler->assemble($request, $contact)));
    }
}
