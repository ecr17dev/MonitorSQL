<?php

namespace App\Http\Controllers;

use App\Models\DatabaseConnection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Chat', [
            'connections' => DatabaseConnection::query()
                ->select(['id', 'name', 'driver', 'host', 'database', 'is_active', 'max_rows'])
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
