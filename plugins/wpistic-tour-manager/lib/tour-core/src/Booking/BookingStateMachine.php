<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Booking;

use Wpistic\TourCore\Booking\Exception\IllegalTransition;

/**
 * Guards the booking lifecycle. Pure: the same rules run under WordPress and Laravel.
 */
final class BookingStateMachine
{
    /** @var array<string, list<BookingStatus>> */
    private const TRANSITIONS = [
        'inquiry'           => [BookingStatus::Quoted, BookingStatus::Cancelled, BookingStatus::Expired],
        'quoted'            => [BookingStatus::DepositLinkSent, BookingStatus::Cancelled, BookingStatus::Expired],
        'deposit_link_sent' => [BookingStatus::DepositPaid, BookingStatus::Expired, BookingStatus::Cancelled],
        'deposit_paid'      => [BookingStatus::Confirmed, BookingStatus::Refunded, BookingStatus::Cancelled],
        'confirmed'         => [BookingStatus::BalanceDue, BookingStatus::Cancelled],
        'balance_due'       => [BookingStatus::PaidInFull, BookingStatus::Cancelled],
        'paid_in_full'      => [BookingStatus::Completed, BookingStatus::Refunded],
        'completed'         => [],
        'expired'           => [],
        'refunded'          => [],
        'cancelled'         => [],
    ];

    /**
     * @return list<BookingStatus>
     */
    public function allowedFrom(BookingStatus $status): array
    {
        return self::TRANSITIONS[$status->value] ?? [];
    }

    public function canTransition(BookingStatus $from, BookingStatus $to): bool
    {
        return in_array($to, $this->allowedFrom($from), true);
    }

    /**
     * @throws IllegalTransition When the move is not allowed.
     */
    public function assert(BookingStatus $from, BookingStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw IllegalTransition::between($from, $to);
        }
    }
}
