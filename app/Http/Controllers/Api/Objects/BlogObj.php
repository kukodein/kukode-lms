<?php

namespace App\Http\Controllers\Api\Objects;

use App\Http\Controllers\Api\Config\ConfigController;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Api\Web\UserController;
use App\Http\Controllers\Api\Web\WebinarController;
use App\Models\Blog;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\ReserveMeeting;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\Webinar;
use App\Models\WebinarChapter;
use App\Models\WebinarFilterOption;
use App\User;

class BlogObj extends Controller
{
    public static function details($query)
    {

        return $query->map(function ($queryy) {
            return self::singleDetails($queryy);
        });
    }

    public static function getById($id)
    {
        $blog = Blog::where('id', $id)->get();
        return self::details($blog)->first();
    }


    public static function singleDetails($blog)
    {
        return [
            'id' => $blog->id,
            'title' => $blog->title,
            'image' => getUrl($blog->image),
            'description' => truncate($blog->description, 160),
            'content' => $blog->content,
            'created_at' => $blog->created_at,
            'author' => UserObj::brief($blog->author, true),
            'comment_count' => $blog->comments->count(),
            'comments' => $blog->comments->map(function ($item) {
                return [
                    'user' => [
                        'full_name' => $item->user->full_name,
                        'avatar' => getUrl($item->user->getAvatar()),
                    ],
                    'create_at' => $item->created_at,
                    'comment' => $item->comment,
                    'replies' => $item->replies->map(function ($reply) {
                        return [
                            'user' => [
                                'full_name' => $reply->user->full_name,
                                'avatar' => getUrl($reply->user->getAvatar()),
                            ],
                            'create_at' => $reply->created_at,
                            'comment' => $reply->comment,
                        ];

                    })
                ];
            }),
            'category' => $blog->category->title,
        ];
    }


}


