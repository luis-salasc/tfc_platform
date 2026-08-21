<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Trainer = 'trainer';
    case Receptionist = 'receptionist';
    case Staff = 'staff';
    case Client = 'client';
}
