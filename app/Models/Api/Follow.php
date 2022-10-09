<?php
namespace App\Models\Api  ;
use App\Models\Follow as PrimaryModel ;

class Follow  extends PrimaryModel{

  
    public function user()
    {
        return $this->belongsTo('App\Models\Api\User', 'user_id', 'id');
    }
}