<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * コーチメモの追加 Action。作成者(author)は認証ユーザー(コーチ / 管理者)。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(Enrollment $enrollment, User $author, array $validated): EnrollmentNote
    {
        return $enrollment->notes()->create([
            'user_id' => $author->id,
            'body' => $validated['body'],
        ]);
    }
}
