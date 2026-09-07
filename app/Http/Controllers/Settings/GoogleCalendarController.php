<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * コーチの Google カレンダー連携 Controller(コーチのみ、ルートの role:coach で担保)。
 *
 * redirect: 認可 URL へ送出(state をセッション保存)
 * callback: state 照合 → 認可コードをトークン交換 → 連携保存
 * destroy: 連携解除
 */
final class GoogleCalendarController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $calendar) {}

    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($this->calendar->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('google_oauth_state');

        // state 不一致(なりすまし / 改ざん)は連携せず拒否
        if ($expected === null || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()
                ->route('settings.availability.index')
                ->with('error', 'Google 連携の検証に失敗しました。もう一度お試しください。');
        }

        $code = (string) $request->query('code');
        if ($code === '') {
            return redirect()
                ->route('settings.availability.index')
                ->with('error', 'Google 連携がキャンセルされました。');
        }

        $token = $this->calendar->exchangeCode($code);
        $this->calendar->storeCredential($request->user(), $token);

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Google カレンダーと連携しました。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->googleCredential?->delete();

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Google カレンダーの連携を解除しました。');
    }
}
