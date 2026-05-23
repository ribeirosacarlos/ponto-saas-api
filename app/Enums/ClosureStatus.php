<?php

namespace App\Enums;

enum ClosureStatus: string
{
    case PROCESSING = 'processing';
    case OPEN = 'open';
    case COMPLETED = 'completed';
}
