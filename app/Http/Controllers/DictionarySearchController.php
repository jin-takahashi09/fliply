<?php

namespace App\Http\Controllers;

use App\Services\DatamuseClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DictionarySearchController extends Controller
{
    public function index(): View
    {
        return view('dictionary.index');
    }

    public function suggestions(Request $request, DatamuseClient $datamuse): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $result = $datamuse->suggest($query);

        if (! $result['ok']) {
            return response()->json([
                'words' => [],
                'message' => '単語を取得できませんでした',
            ]);
        }

        return response()->json([
            'words' => $result['words'],
            'message' => null,
        ]);
    }
}
