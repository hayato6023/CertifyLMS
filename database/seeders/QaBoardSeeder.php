<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * 開発用 質問掲示板シーダー。
 *
 * **設計思想(状態網羅 + 固定アカウント)**:
 *
 * 1. **公開済資格ごとにスレッドを散布**: 未解決 / 解決済を混在させ、回答数(0 件 / 数件)と
 *    作成日時をばらつかせる。絞り込み(解決状態 / 資格 / キーワード)・新着順・ページネーション・
 *    削除の動作を安定して確認できるようにする。
 *
 * 2. **固定 student を投稿者にしたスレッドを用意**: 「自分の質問」動線・解決マークの操作を確認する。
 *
 * 3. **回答は受講生・コーチが混在**: コーチ回答バッジ / 自分の回答バッジの表示を確認する。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder`(担当コーチ割当含む) → 本 Seeder。
 */
final class QaBoardSeeder extends Seeder
{
    public function run(): void
    {
        $publishedCertifications = Certification::query()
            ->with('coaches')
            ->where('status', CertificationStatus::Published->value)
            ->get();

        if ($publishedCertifications->isEmpty()) {
            $this->command?->warn('QaBoardSeeder: 公開済資格が存在しません。先に CertificationSeeder を実行してください。');

            return;
        }

        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->inRandomOrder()
            ->limit(8)
            ->get();

        if ($students->isEmpty()) {
            $this->command?->warn('QaBoardSeeder: 受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        foreach ($publishedCertifications as $index => $certification) {
            $this->seedThreadsForCertification($certification, $students, $fixedStudent, $index);
        }
    }

    /**
     * 1 資格あたり 3 件のスレッドを、未解決(未回答) / 未解決(回答あり) / 解決済 の 3 パターンで作る。
     *
     * @param Collection<int, User> $students
     */
    private function seedThreadsForCertification(
        Certification $certification,
        $students,
        ?User $fixedStudent,
        int $index,
    ): void {
        $coach = $certification->coaches->first();

        // 1) 未解決・未回答（新着扱い）
        $this->makeThread(
            certification: $certification,
            author: $index === 0 && $fixedStudent !== null ? $fixedStudent : $students->random(),
            status: QaThreadStatus::Open,
            createdDaysAgo: 1,
            replyAuthors: [],
        );

        // 2) 未解決・回答あり（対応中）
        $replyAuthors = [];
        if ($coach !== null) {
            $replyAuthors[] = $coach;
        }
        $replyAuthors[] = $students->random();
        $this->makeThread(
            certification: $certification,
            author: $students->random(),
            status: QaThreadStatus::Open,
            createdDaysAgo: 5,
            replyAuthors: $replyAuthors,
        );

        // 3) 解決済・回答あり
        $this->makeThread(
            certification: $certification,
            author: $index === 0 && $fixedStudent !== null ? $fixedStudent : $students->random(),
            status: QaThreadStatus::Resolved,
            createdDaysAgo: 12,
            replyAuthors: $coach !== null ? [$coach] : [$students->random()],
        );
    }

    /**
     * @param array<int, User> $replyAuthors
     */
    private function makeThread(
        Certification $certification,
        User $author,
        QaThreadStatus $status,
        int $createdDaysAgo,
        array $replyAuthors,
    ): void {
        $createdAt = Carbon::now()->subDays($createdDaysAgo)->subHours(random_int(0, 12));
        $resolvedAt = $status === QaThreadStatus::Resolved
            ? (clone $createdAt)->addDays(random_int(1, 3))
            : null;

        $thread = QaThread::create([
            'certification_id' => $certification->id,
            'user_id' => $author->id,
            'title' => $this->sampleTitle($certification),
            'body' => $this->sampleBody(),
            'status' => $status->value,
            'resolved_at' => $resolvedAt,
        ]);
        $thread->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        foreach ($replyAuthors as $offset => $replyAuthor) {
            $replyAt = (clone $createdAt)->addHours(($offset + 1) * random_int(2, 8));
            $reply = QaReply::create([
                'qa_thread_id' => $thread->id,
                'user_id' => $replyAuthor->id,
                'body' => $this->sampleReplyBody(),
            ]);
            $reply->forceFill(['created_at' => $replyAt, 'updated_at' => $replyAt])->save();
        }
    }

    private function sampleTitle(Certification $certification): string
    {
        $samples = [
            $certification->name.'の学習範囲でつまずいた点について',
            '過去問の解き方のコツを教えてください',
            'この分野の頻出パターンが整理できません',
            '模試の復習方法についてアドバイスがほしいです',
            '用語の違いがイメージできず混乱しています',
        ];

        return $samples[array_rand($samples)];
    }

    private function sampleBody(): string
    {
        return "教材を一通り読みましたが、実際の問題に落とし込むところでつまずいています。\n"
            ."特に応用問題になると、どの知識をどう組み合わせればよいか分からなくなります。\n"
            .'自分で調べた範囲と、それでも分からない点を整理しました。アドバイスをいただけると助かります。';
    }

    private function sampleReplyBody(): string
    {
        $samples = [
            "まずは基本パターンを 3 つに絞って、それぞれの例題を繰り返すのがおすすめです。\n応用はそのあとで大丈夫です。",
            'その分野は「定義 → 具体例 → 反例」の順で整理すると混乱しにくいですよ。',
            '過去問は解いた後の振り返りが一番大事です。間違えた選択肢がなぜ違うかを言語化してみてください。',
        ];

        return $samples[array_rand($samples)];
    }
}
