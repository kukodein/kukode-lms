<?php

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Api\Objects\BlogObj;
use App\Http\Controllers\Api\Objects\UserObj;
use App\Http\Controllers\Api\Objects\WebinarObj;
use App\Models\Api\Comment;
use App\Models\CommentReport;
use App\Models\Webinar;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentsController extends Controller
{
    public function list(Request $request)
    {
        $data = [
            'my_comment' => $this->myComments($request),
            'class_comment' => $this->myClassComments($request),
        ];
        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'), $data);
    }

    public static function details($comments)
    {
        $comments = $comments->map(function ($item) {
            return [
                'webinar'=>WebinarObj::getSingle($item->webinar_id) ,
                'blog'=>BlogObj::getById($item->blog_id),
                'id' => $item->id,
                'user' => UserObj::obj($item->user_id),
                'status' => ($item->reply_id) ? 'replied' : 'open',
                'create_at' => $item->created_at,
                'comment' => $item->comment,
                'replies' => $item->replies->map(function ($reply) {
                    return [
                        'user' => [
                            'full_name' => $reply->user->full_name,
                            'avatar' => $reply->user->getAvatar(),
                        ],
                        'create_at' => $reply->created_at,
                        'comment' => $reply->comment,
                    ];

                })
            ];
        });

        return $comments;
    }

    public function myClassComments(Request $request)
    {
        $user = apiAuth();

        $userWebinarsIds = $user->webinars->pluck('id')->toArray();

        $comments=Comment::whereIn('webinar_id', $userWebinarsIds)
        ->where('status', 'active')
        ->handleFilters()->orderBy('created_at', 'desc')
        ->get() ;
        foreach ($comments->whereNull('viewed_at') as $comment) {
            $comment->update([
                'viewed_at' => time()
            ]);
        }
        $comments=$comments->map(function($comment){
            return $comment->details ;
        }) ; 
        return $comments ; 
 
    }

    public function myComments(Request $request)
    {
        $user = apiAuth();

        $query = Comment::where('user_id', $user->id);

        $webinar_query = clone $query;
        $webinar_comments = $webinar_query->whereNotNull('webinar_id')
        ->handleFilters()->orderBy('created_at', 'desc')
        ->get()->map(function ($comment){
            return $comment->details ;
        }) ;
        ;


        $blog_comments = clone $query;
        $blog_comments = $blog_comments->whereNotNull('blog_id')
        ->handleFilters()->orderBy('created_at', 'desc')
        ->get()->map(function ($comment){
            return $comment->details ;
        }) ;
        

        return ['blogs' => $blog_comments, 'webinar' => $webinar_comments];

    }

    private function handleFilter($query, $request)
    {
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

    public function store(Request $request)
    {
        $rules = [
            'item_id' => 'required',
            'item_name' => ['required', Rule::in(['blog', 'webinar'])],
            'comment' => 'required|string',
        ];


        $item_name = $request->input('item_name');
        $item_id = $request->input('item_id');

        if ($item_name == 'webinar') {
            $rules['item_id'] = 'required|exists:webinars,id';
        } elseif ($item_name == 'blog') {
            $rules['item_id'] = 'required|exists:blog,id';

        }
        validateParam($request->all(), $rules);


        $user = auth('api')->user();
        $item_name = $item_name . '_id';

        Comment::create([
            $item_name => $item_id,
            'user_id' => $user->id,
            'comment' => $request->input('comment'),
            'reply_id' => $request->input('reply_id'),
            'status' => $request->input('status') ?? Comment::$pending,
            'created_at' => time()
        ]);

        if ($item_name == 'webinar_id') {
            $webinar = Webinar::FindOrFail($item_id);
            $notifyOptions = [
                '[c.title]' => $webinar->title,
                '[u.name]' => $user->full_name
            ];
            sendNotification('new_comment', $notifyOptions, 1);
        }

        return apiResponse2(1, 'stored', trans('public.stored'));
    }

    public function update(Request $request, $id)
    {
        validateParam($request->all(), [
            'comment' => 'required',
        ]);

        $user = apiAuth();

        $comment = Comment::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (empty($comment)) {
            abort(404);
        }
        $comment->update([
            'comment' => $request->input('comment'),
            'status' => 'pending',
        ]);
        return apiResponse2(1, 'updated', trans('api.public.updated'));

    }

    public function destroy(Request $request, $id)
    {
        $user = auth('api')->user();
        $comment = Comment::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
        if (empty($comment)) {
            // dd('ff');
            abort(404);
            return apiResponse(0, 'there is no such comment for current user');

        }

        $comment->delete();

        return apiResponse2(1, 'deleted', trans('public.deleted'));

        return apiResponse(1, 'comment deleted successfully');
    }

    public function reply(Request $request, $id)
    {
        validateParam($request->all(), [
            'reply' => 'required',
        ]);

        $user = apiAuth();
        $userWebinarsIds = $user->webinars->pluck('id')->toArray();

        $comment = Comment::where('id', $id)
            ->where(function ($query) use ($user, $userWebinarsIds) {
                //  $query->where('user_id', $user->id)
                //      ->orWhereIn('webinar_id', $userWebinarsIds);
            })->first();

            
            $status='pending' ;
         //   dd($userWebinarsIds) ;
            if(in_array($comment->webinar_id ,$userWebinarsIds)){
              $status='active' ;
            }

        if (!$comment) {
            abort(404);
        }


        // $item_name = ($comment->webinar_id) ? 'webinar_id' : 'blog_id';
        Comment::create([
            'user_id' => $user->id,
            'comment' => $request->input('reply'),
            // 'webinar_id' => $comment->webinar_id,
            'reply_id' => $comment->id,
            'status' => $status,
            'created_at' => time()
        ]);
        return apiResponse2(1, 'stored', trans('public.stored'));

    }

    public function report(Request $request, $id)
    {
        validateParam($request->all(), [
            'message' => 'required|string'
        ]);

        $user = apiAuth();

        $userWebinarsIds = $user->webinars->pluck('id')->toArray();

        $comment = Comment::where('id', $id)
           // ->where(function ($query) use ($user, $userWebinarsIds) {
            //    $query->where('user_id', $user)
            //        ->orWhereIn('webinar_id', $userWebinarsIds);
          //  })
            ->first();
            if(!$comment->reply_id)
            {
                $comment = Comment::find($comment->reply_id) ;
                 

            }

        if (!empty($comment)) {

            CommentReport::create([
                'webinar_id' =>($comment->reply_id)?Comment::find($comment->reply_id)->webinar_id :$comment->webinar_id,
                'blog_id' =>($comment->reply_id)?Comment::find($comment->reply_id)->blog_id :$comment->blog_id,

                //'blog_id'=>$comment->blog_id ,
                'user_id' => $user->id,
                'comment_id' => $comment->id,
                'message' => $request->input('message'),
                'created_at' => time()
            ]);

            return apiResponse2(1, 'stored', trans('api.public.stored'));
        }
        abort(404);
        return apiResponse2(0, 'failed', trans('api.public.failed'));

    }

}
