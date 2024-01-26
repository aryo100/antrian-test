<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Antrian extends Model
{
    use HasFactory;
    protected $table = 'antriansoal';
    protected $primaryKey = 'nomorkartu';
    public $incrementing = false;
    protected $guarded = [];
}
