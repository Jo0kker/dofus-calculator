<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;

class User extends BaseUser
{
    use HasApiTokens;
}
