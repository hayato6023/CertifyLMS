<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 修了証 PDF ダウンロード Controller。
 *
 * 認可は CertificatePolicy(本人 / 担当コーチ / 管理者)に委譲する。
 * PDF ファイルが保管領域に見つからない場合は 404。
 */
final class CertificateController extends Controller
{
    public function download(Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        abort_unless(
            Storage::disk('local')->exists($certificate->pdf_path),
            Response::HTTP_NOT_FOUND,
        );

        $certificate->loadMissing('certification');
        $filename = '修了証_'.($certificate->certification->name ?? 'certificate').'.pdf';

        return Storage::disk('local')->download($certificate->pdf_path, $filename);
    }
}
