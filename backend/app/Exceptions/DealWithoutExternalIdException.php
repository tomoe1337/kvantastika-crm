<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class DealWithoutExternalIdException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'This deal has no external CRM id.'], 422);
    }
}
