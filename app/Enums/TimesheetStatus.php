<?php

namespace App\Enums;

enum TimesheetStatus: string
{
    case PENDING_EMPLOYEE = 'pending_employee';
    case DISPUTED = 'disputed';
    case PENDING_MANAGER = 'pending_manager';
    case COMPLETED = 'completed';
}
