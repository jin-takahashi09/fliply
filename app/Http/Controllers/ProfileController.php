<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const MAX_AVATAR_BYTES = 1048576;

    /**
     * @var list<string>
     */
    private const ALLOWED_AVATAR_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'avatarVersion' => $request->user()->avatarVersion(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'avatar' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:1024'],
            ],
            [
                'name.required' => '名前を入力してください。',
                'name.max' => '名前は255文字以内で入力してください。',
                'avatar.mimes' => 'JPEG、PNG、WebPの画像のみ使用できます。',
                'avatar.max' => '画像は1MB以下にしてください。',
            ],
        );

        DB::transaction(function () use ($request, $validated): void {
            $request->user()->update([
                'name' => $validated['name'],
            ]);

            if ($request->hasFile('avatar')) {
                $this->storeAvatar($request->user(), $request->file('avatar'));
            }
        });

        return redirect()->route('home');
    }

    public function avatar(Request $request): Response
    {
        $avatar = $request->user()->avatar()->first();

        if ($avatar === null) {
            abort(404);
        }

        $contents = $avatar->binaryContents();

        if ($contents === '') {
            abort(404);
        }

        return response($contents, 200, [
            'Content-Type' => $avatar->mime_type,
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $request->user()->avatar()->delete();

        return redirect()->route('profile.edit');
    }

    private function storeAvatar(User $user, UploadedFile $file): void
    {
        $mime = $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_AVATAR_MIMES, true)) {
            throw ValidationException::withMessages([
                'avatar' => 'JPEG、PNG、WebPの画像のみ使用できます。',
            ]);
        }

        $contents = $file->getContent();

        if ($contents === '') {
            throw ValidationException::withMessages([
                'avatar' => '画像を読み込めませんでした。',
            ]);
        }

        if (strlen($contents) > self::MAX_AVATAR_BYTES) {
            throw ValidationException::withMessages([
                'avatar' => '画像は1MB以下にしてください。',
            ]);
        }

        $user->avatar()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'mime_type' => $mime,
                'image_data' => $contents,
            ],
        );
    }
}
