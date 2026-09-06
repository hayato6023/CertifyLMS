<?php

declare(strict_types=1);

namespace Tests\Feature\Certificate;

use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Certificate\IssueAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 修了証 PDF の生成・ダウンロード(S-A-04)の検証。
 *
 * - 修了証発行時に PDF 実体が local ディスクに生成される
 * - ダウンロード認可: 本人 / 担当コーチ / 管理者は可、他受講生 / 担当外コーチは不可
 * - PDF ファイルが無い場合は 404
 */
class DownloadCertificateTest extends TestCase
{
    use RefreshDatabase;

    private function passedEnrollment(User $student, Certification $certification): Enrollment
    {
        return Enrollment::factory()->for($student)->for($certification)->create([
            'status' => EnrollmentStatus::Passed,
            'passed_at' => now(),
        ]);
    }

    private function issueCertificate(User $student, Certification $certification): Certificate
    {
        $enrollment = $this->passedEnrollment($student, $certification);

        return app(IssueAction::class)($enrollment);
    }

    public function test_issue_generates_pdf_file(): void
    {
        Storage::fake('local');
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certificate = $this->issueCertificate($student, $certification);

        Storage::disk('local')->assertExists($certificate->pdf_path);
    }

    public function test_owner_can_download(): void
    {
        Storage::fake('local');
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $certificate = $this->issueCertificate($student, $certification);

        $this->actingAs($student)->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_other_student_cannot_download(): void
    {
        Storage::fake('local');
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $certificate = $this->issueCertificate($student, $certification);

        $other = User::factory()->student()->inProgress()->create();
        $this->actingAs($other)->get(route('certificates.download', $certificate))->assertForbidden();
    }

    public function test_assigned_coach_can_download(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $certificate = $this->issueCertificate($student, $certification);

        $coach = User::factory()->coach()->inProgress()->create();
        $coach->assignedCertifications()->attach($certification, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($coach)->get(route('certificates.download', $certificate))->assertOk();
    }

    public function test_unassigned_coach_cannot_download(): void
    {
        Storage::fake('local');
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $certificate = $this->issueCertificate($student, $certification);

        $coach = User::factory()->coach()->inProgress()->create();
        $this->actingAs($coach)->get(route('certificates.download', $certificate))->assertForbidden();
    }

    public function test_admin_can_download(): void
    {
        Storage::fake('local');
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $certificate = $this->issueCertificate($student, $certification);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('certificates.download', $certificate))->assertOk();
    }

    public function test_missing_file_returns_404(): void
    {
        Storage::fake('local');
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $certificate = $this->issueCertificate($student, $certification);

        // 生成された PDF を消してからダウンロードすると 404
        Storage::disk('local')->delete($certificate->pdf_path);

        $this->actingAs($student)->get(route('certificates.download', $certificate))->assertNotFound();
    }
}
