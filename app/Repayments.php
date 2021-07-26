<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Repayments extends Model
{
    //
    protected $table = 'repayments';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'loan_id', 
        'payment',
    ];

    public function loan() {
        return $this->hasOne('App\Loans', 'id', 'loan_id');
    }
}
