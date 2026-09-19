<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 公開 Part1(Chapter1: Section×2)/ Part2(Chapter2: Section×2) の計 4 Section を持つ資格を組む。
     *
     * @return array{cert: Certification, sections: array<int, Section>}
     */
    private function buildContentTree(): array
    {
        $cert = Certification::factory()->published()->create();

        $part1 = Part::factory()->published()->forCertification($cert)->create();
        $chapter1 = Chapter::factory()->published()->forPart($part1)->create();
        $s1 = Section::factory()->published()->forChapter($chapter1)->create();
        $s2 = Section::factory()->published()->forChapter($chapter1)->create();

        $part2 = Part::factory()->published()->forCertification($cert)->create();
        $chapter2 = Chapter::factory()->published()->forPart($part2)->create();
        $s3 = Section::factory()->published()->forChapter($chapter2)->create();
        $s4 = Section::factory()->published()->forChapter($chapter2)->create();

        return ['cert' => $cert, 'sections' => [$s1, $s2, $s3, $s4]];
    }

    private function markRead(Enrollment $enrollment, Section $section): void
    {
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($section)->create();
    }

    public function test_summarize_with_no_progress_returns_zero_ratios(): void
    {
        $tree = $this->buildContentTree();
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($tree['cert'])->learning()->create();

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(4, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);
        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(2, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_counts_chapter_and_part_completion(): void
    {
        $tree = $this->buildContentTree();
        [$s1, $s2, $s3, $s4] = $tree['sections'];
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($tree['cert'])->learning()->create();

        // Part1/Chapter1 の 2 Section を読了 → Chapter1・Part1 は完了、Chapter2・Part2 は未完
        $this->markRead($enrollment, $s1);
        $this->markRead($enrollment, $s2);

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(4, $summary->sectionsTotal);
        $this->assertSame(2, $summary->sectionsCompleted);
        $this->assertSame(0.5, $summary->sectionCompletionRatio);
        // Chapter1 は 2/2 読了で完了、Chapter2 は 0/2
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(0.5, $summary->chapterCompletionRatio);
        // Part1 配下は全読了で完了、Part2 は未完
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertSame(0.5, $summary->partCompletionRatio);
        // overall は Section 比率
        $this->assertSame(0.5, $summary->overallCompletionRatio);
    }

    public function test_summarize_partial_chapter_is_not_completed(): void
    {
        $tree = $this->buildContentTree();
        [$s1, $s2, $s3, $s4] = $tree['sections'];
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($tree['cert'])->learning()->create();

        // Chapter1 の片方だけ読了 → Chapter1 は完了扱いにならない
        $this->markRead($enrollment, $s1);

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(0.25, $summary->sectionCompletionRatio);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0, $summary->partsCompleted);
    }

    public function test_summarize_ignores_unpublished_sections(): void
    {
        $tree = $this->buildContentTree();
        $cert = $tree['cert'];
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($cert)->learning()->create();

        // 下書き Section を追加しても総数に含まれない
        $draftPart = Part::factory()->published()->forCertification($cert)->create();
        $draftChapter = Chapter::factory()->published()->forPart($draftPart)->create();
        Section::factory()->draft()->forChapter($draftChapter)->create();

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(4, $summary->sectionsTotal);
    }

    public function test_batch_section_ratios_returns_ratio_per_enrollment(): void
    {
        $treeA = $this->buildContentTree();
        $treeB = $this->buildContentTree();
        $student = User::factory()->student()->create();

        $enrollmentA = Enrollment::factory()->for($student, 'user')->for($treeA['cert'])->learning()->create();
        $enrollmentB = Enrollment::factory()->for($student, 'user')->for($treeB['cert'])->learning()->create();

        // A は 1/4、B は 4/4 読了
        $this->markRead($enrollmentA, $treeA['sections'][0]);
        foreach ($treeB['sections'] as $section) {
            $this->markRead($enrollmentB, $section);
        }

        $ratios = app(ProgressService::class)->batchSectionRatios(
            new EloquentCollection([$enrollmentA, $enrollmentB]),
        );

        $this->assertSame(0.25, $ratios[$enrollmentA->id]);
        $this->assertSame(1.0, $ratios[$enrollmentB->id]);
    }

    public function test_batch_section_ratios_returns_empty_for_empty_input(): void
    {
        $ratios = app(ProgressService::class)->batchSectionRatios(new EloquentCollection);

        $this->assertSame([], $ratios);
    }
}
