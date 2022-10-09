<?php

namespace App\Http\Controllers\Api\Objects;

class SupportObj extends Obj
{
    static public function getById($obj)
    {

    }

    static public function brief($obj)
    {
        return [
            'id' => $obj->id,
            'title' => $obj->title,
            'status' => $obj->status,
            'type' => ($obj->webinar_id) ? 'course_support' : 'platform_support',
            'webinar' => CourseObj::getBriefById($obj->webinar_id),
            'conversations' => $obj->conversations->map(function ($conversation) {
                return [
                    'message' => $conversation->message,
                    'sender' =>($conversation->sender_id) ?[
                        'id' => $conversation->sender->id,
                        'full_name' => $conversation->sender->full_name,
                        'avatar' => $conversation->sender->getAvatar(),
                    ]:null ,
                    'supporter' => ($conversation->supporter_id) ? [
                        'id' => $conversation->supporter->id,
                        'full_name' => $conversation->supporter->full_name,
                        'avatar' => $conversation->supporter->getAvatar(),
                    ] : null,
                    'created_at' => $conversation->created_at
                ];

            }),
            'department' => $obj->department->title??null,
            'created_at' => $obj->created_at,
        ];
    }

    static public function details($obj)
    {


    }

    static public function getBriefById($obj){

    }
    static public function getDetailsById($obj){

    }

}
