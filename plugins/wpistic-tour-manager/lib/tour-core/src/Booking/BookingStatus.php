<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Booking;

/**
 * The booking lifecycle. The trip is always human-confirmed — there is no path
 * straight to Confirmed from a guest action; a team member confirms availability.
 */
enum BookingStatus: string
{
    case Inquiry = 'inquiry';
    case Quoted = 'quoted';
    case DepositLinkSent = 'deposit_link_sent';
    case DepositPaid = 'deposit_paid';
    case Confirmed = 'confirmed';
    case BalanceDue = 'balance_due';
    case PaidInFull = 'paid_in_full';
    case Completed = 'completed';
    case Expired = 'expired';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Expired, self::Refunded, self::Cancelled], true);
    }

    /**
     * Whether this status holds a departure seat (capacity is decremented while held).
     */
    public function holdsCapacity(): bool
    {
        return in_array(
            $this,
            [self::DepositPaid, self::Confirmed, self::BalanceDue, self::PaidInFull],
            true
        );
    }
}
