<?php

namespace App\Support;

use Illuminate\Http\Request;

class SessionUserResolver
{
    public function resolve(Request $request): array
    {
        $user = $request->session()->get('user', []);

        return [
            'id' => $user['id'] ?? null,
            'name' => $user['username'] ?? null,
        ];
    }
}
