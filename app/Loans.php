<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Loans extends Model
{
    //
    protected $table = 'loans';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'user_id', 
        'original_payment',
        'duration',
        'interest_rate',
        'arrangement_fee',
        'pay_per_month',
        'final_payment',
        'left_over',
        'start_date',
        'end_date'
    ];

    public function user() {
        return $this->hasOne('App\User', 'id', 'user_id');
    }

    public function repayments() {
        return $this->hasMany('App\Repayments', 'loan_id', 'id');
    }
}
