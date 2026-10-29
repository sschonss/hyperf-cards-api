<?php

declare(strict_types=1);

namespace App\Model;

final class TransactionStatus
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const FAILED = 'failed';
}
