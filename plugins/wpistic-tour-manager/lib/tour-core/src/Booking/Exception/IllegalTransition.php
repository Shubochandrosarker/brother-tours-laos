<?php

declare(strict_types=1);

namespace Wpistic\TourCore\Booking\Exception;

use DomainException;
use Wpistic\TourCore\Booking\BookingStatus;

final class IllegalTransition extends DomainException
{
    public static function between(BookingStatus $from, BookingStatus $to): self
    {
        return new self(sprintf('Cannot move a booking from "%s" to "%s".', $from->value, $to->value));
    }
}
