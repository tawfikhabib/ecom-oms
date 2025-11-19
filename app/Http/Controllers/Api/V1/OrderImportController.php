<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderImportController extends Controller
{
    /**
     * Order import endpoint has been removed. Return 410 Gone.
     */
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Order import endpoint removed. Use product import instead.'
        ], 410);
    }
}
