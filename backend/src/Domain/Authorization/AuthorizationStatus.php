<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

enum AuthorizationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Reversed = 'reversed';
}
