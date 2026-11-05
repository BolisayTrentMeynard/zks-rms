<?php

namespace App\Enums;

enum ActorType: string
{
    case Guest = 'guest';
    case Admin = 'admin';
    case System = 'system';
}
