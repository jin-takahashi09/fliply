<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DictionaryWord extends Model
{
    public $timestamps = false;

    protected $fillable = ['word'];
}
