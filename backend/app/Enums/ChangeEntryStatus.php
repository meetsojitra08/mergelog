<?php

namespace App\Enums;

enum ChangeEntryStatus: string
{
    case PendingAi = 'pending_ai';
    case Drafted = 'drafted';
    case Failed = 'failed';
}
