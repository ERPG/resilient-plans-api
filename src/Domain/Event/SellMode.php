<?php

declare(strict_types=1);

namespace App\Domain\Event;

enum SellMode: string
{
    case Online  = 'online';
    case Offline = 'offline';
}
