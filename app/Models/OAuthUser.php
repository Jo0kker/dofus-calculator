<?php

namespace App\Models;

use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class OAuthUser extends BaseUser implements OAuthenticatable
{
    use HasApiTokens;
}
