<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case OPEN = 'open';
    case RESOLVED = 'resolved';
}
