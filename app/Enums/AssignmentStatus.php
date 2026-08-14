<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::DECLINED => 'Declined',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::OVERDUE => 'Overdue',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::ACCEPTED => 'blue',
            self::DECLINED => 'red',
            self::IN_PROGRESS => 'purple',
            self::COMPLETED => 'green',
            self::OVERDUE => 'orange',
            self::CANCELLED => 'gray',
        };
    }
}
