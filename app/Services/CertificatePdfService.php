<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;

/**
 * 修了証 PDF を生成し、プライベート保管領域(local ディスク)に保存するサービス。
 *
 * テンプレートは resources/views/certificates/pdf.blade.php。mpdf で日本語 PDF を生成し、
 * Certificate.pdf_path のパスに保存する。生成に失敗した場合は例外を送出し、
 * 呼び出し元(IssueAction)のトランザクションをロールバックさせる(= 発行しない)。
 */
final class CertificatePdfService
{
    public function generate(Certificate $certificate): void
    {
        $certificate->loadMissing(['user', 'certification']);

        $html = View::make('certificates.pdf', ['certificate' => $certificate])->render();

        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);
        $mpdf->WriteHTML($html);

        Storage::disk('local')->put($certificate->pdf_path, $mpdf->Output('', 'S'));
    }
}
