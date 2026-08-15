<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Tests\Booking;

use PHPUnit\Framework\TestCase;
use Wpistic\TourCore\Booking\BookingStateMachine;
use Wpistic\TourCore\Booking\BookingStatus;
use Wpistic\TourCore\Booking\Exception\IllegalTransition;

final class BookingStateMachineTest extends TestCase
{
    private BookingStateMachine $sm;

    protected function setUp(): void
    {
        $this->sm = new BookingStateMachine();
    }

    public function testHappyPath(): void
    {
        $chain = [
            BookingStatus::Inquiry,
            BookingStatus::Quoted,
            BookingStatus::DepositLinkSent,
            BookingStatus::DepositPaid,
            BookingStatus::Confirmed,
            BookingStatus::BalanceDue,
            BookingStatus::PaidInFull,
            BookingStatus::Completed,
        ];

        $count = count($chain);
        for ($i = 0; $i < $count - 1; $i++) {
            self::assertTrue(
                $this->sm->canTransition($chain[$i], $chain[$i + 1]),
                $chain[$i]->value . ' -> ' . $chain[$i + 1]->value
            );
        }
    }

    public function testNoSelfServeConfirmation(): void
    {
        // The trip is human-confirmed: no guest action jumps straight to Confirmed.
        self::assertFalse($this->sm->canTransition(BookingStatus::Inquiry, BookingStatus::Confirmed));
        self::assertFalse($this->sm->canTransition(BookingStatus::DepositLinkSent, BookingStatus::Confirmed));
    }

    public function testRefundFromDepositPaid(): void
    {
        self::assertTrue($this->sm->canTransition(BookingStatus::DepositPaid, BookingStatus::Refunded));
    }

    public function testTerminalStatesHaveNoMoves(): void
    {
        self::assertSame([], $this->sm->allowedFrom(BookingStatus::Cancelled));
        self::assertTrue(BookingStatus::Completed->isTerminal());
    }

    public function testAssertThrowsOnIllegalMove(): void
    {
        $this->expectException(IllegalTransition::class);
        $this->sm->assert(BookingStatus::Completed, BookingStatus::Inquiry);
    }

    public function testCapacityHeldStates(): void
    {
        self::assertTrue(BookingStatus::DepositPaid->holdsCapacity());
        self::assertFalse(BookingStatus::Inquiry->holdsCapacity());
    }
}
