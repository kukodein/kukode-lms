<?php
namespace App\Models\Api ;
use App\Models\Comment  as Model ;

class Comment extends Model{

    public function  getDetailsAttribute(){


        // 'webinar'=>WebinarObj::getSingle($item->webinar_id) ,
        // 'blog'=>BlogObj::getById($item->blog_id),
        // 'id' => $item->id,
        // 'user' => UserObj::obj($item->user_id),
        // 'status' => ($item->reply_id) ? 'replied' : 'open',
        // 'create_at' => $item->created_at,
        // 'comment' => $item->comment,
        // 'replies' => $item->replies->map(function ($reply) {
        //     return [
        //         'user' => [
        //             'full_name' => $reply->user->full_name,
        //             'avatar' => $reply->user->getAvatar(),
        //         ],
        //         'create_at' => $reply->created_at,
        //         'comment' => $reply->comment,
        //     ];

        // })

        return [
            'id'=>$this->id ,
            'status'=>$this->status ,

            'user' => $this->user->brief,
            'create_at' => $this->created_at,
            'comment' => $this->comment,
            'blog'=>$this->blog->brief??null ,
            'webinar'=>$this->webinar->brief??null ,

            'replies' => $this->replies->where('status','active')->map(function ($reply) {
                return [
                    'user' => $reply->user->brief,
                    'create_at' => $reply->created_at,
                    'comment' => $reply->comment,
                ];

            })
        ] ;
    }

    public function scopeHandleFilters( $query)
    {
        $request=request() ;
        $from = $request->get('from', null);
        $to = $request->get('to', null);
        $user = $request->get('user', null);
        $webinar = $request->get('webinar', null);
        $filter_new_comments = request()->get('new_comments', null);

        if (!empty($from) and !empty($to)) {
            $from = strtotime($from);
            $to = strtotime($to);

            $query->whereBetween('created_at', [$from, $to]);
        } else {
            if (!empty($from)) {
                $from = strtotime($from);

                $query->where('created_at', '>=', $from);
            }

            if (!empty($to)) {
                $to = strtotime($to);

                $query->where('created_at', '<', $to);
            }
        }

        if (!empty($user)) {
            $usersIds = User::where('full_name', 'like', "%$user%")->pluck('id')->toArray();

            $query->whereIn('user_id', $usersIds);
        }

        if (!empty($webinar)) {
            $webinarsIds = Webinar::where('title', 'like', "%$webinar%")->pluck('id')->toArray();

            $query->whereIn('webinar_id', $webinarsIds);
        }

        if (!empty($filter_new_comments) and $filter_new_comments == 'on') {

        }

        return $query;
    }


    public function replies()
    {
        return $this->hasMany($this, 'reply_id', 'id');
    }

    public function webinar()
    {
        return $this->belongsTo('App\Models\Api\Webinar', 'webinar_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\Api\User', 'user_id', 'id');
    }

    public function review()
    {
        return $this->belongsTo('App\Models\Api\WebinarReview', 'review_id', 'id');
    }

    public function blog()
    {
        return $this->belongsTo('App\Models\Api\Blog', 'blog_id', 'id');
    }
}
?>