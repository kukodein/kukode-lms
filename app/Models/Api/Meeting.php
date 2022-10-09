<?php
namespace App\Models\Api  ;
use App\Models\Meeting as PrimaryModel ;
use App\Http\Controllers\Api\Config\ConfigController ;

class Meeting  extends PrimaryModel{

    public function getDetailsAttribute(){

        return [
            'id' => $this->id,
            'disabled' => $this->disabled,
            'discount' => $this->discount,
            //  'price' =>ConfigController::get()['currency']['sign']. $user->meeting->amount,
            'price' =>$this->amount,
         'price_with_discount' => ($this->discount) ? ($this->amount - (($this->amount * $this->discount) / 100)) : $this->amount,
            'timing' => $this->meetingTimes->map(function ($time) {
                return [
                    'id' => $time->id,
                    'day_label' => $time->day_label,
                    'time' => $time->time,
                ];
            }),
            'timing_group_by_day' => $this->meetingTimes->groupBy('day_label')->map(function ($time) {
                return $time->map(function ($ee) {
                    return [
                        'id' => $ee->id,
                        'day_label' => $ee->day_label,
                        'time' => $ee->time,
                    ];
                });

            }),

        ]  ;

    }

    public function teacher()
    {
        return $this->belongsTo('App\Models\Api\User', 'teacher_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo('App\Models\Api\User', 'creator_id', 'id');
    }


    
}