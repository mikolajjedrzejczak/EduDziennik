<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class DomainModel extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @var list<string> */
    protected $guarded = ['id'];
}
