<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;


    protected $table = 'get_visitors'; // optional if table name is not plural of model

    protected $primaryKey = 'visitor_id'; //set your custom primary key

    public $incrementing = true; // default is true; set to false if UUID or string

    protected $keyType = 'int';

    protected $fillable = [
        'visit_id',
        'name',
        'mobileno',
        'email',
        'address',
        'purpose',
        'whomtomeet',
        'token',
        'short_name',
        'user_id',
        'token_created_at',
        'academic_yr',
        'visit_date',
        'visit_in_time',
        'visit_out_time',

    ];
}
