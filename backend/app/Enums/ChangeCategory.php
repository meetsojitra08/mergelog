<?php

namespace App\Enums;

enum ChangeCategory: string
{
    case Feature = 'feature';
    case Fix = 'fix';
    case Breaking = 'breaking';
    case Other = 'other';
}
