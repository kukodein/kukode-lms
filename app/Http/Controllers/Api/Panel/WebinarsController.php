<?php

namespace App\Http\Controllers\Api\Panel;

use App\Exports\WebinarStudents;
use App\Http\Controllers\Api\Controller;
use App\Models\Category;
use App\Models\Faq;
use App\Models\File;
use App\Models\Prerequisite;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Session;
use App\Models\Tag;
use App\Models\TextLesson;
use App\Models\Ticket;
use App\Models\Translation\WebinarTranslation;
use App\Models\WebinarChapter;
use App\User;
use App\Models\Api\Webinar;
use App\Models\WebinarPartnerTeacher;
use App\Models\WebinarFilterOption;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Validator;

class WebinarsController extends Controller
{
     
    public function list(Request $request,$id=null)
    {
        return [
            'my_classes' => $this->myClasses($request),
            'purchases' => $this->purchases($request),
            'organizations' => $this->organizations($request),
            'invitations' => $this->invitations($request),
        ];
    }

    public function myClasses(Request $request)
    {
        $user = apiAuth();
        
        $webinars = Webinar::where(function ($query) use ($user) {

            if ($user->isTeacher()) {
                $query->where('teacher_id', $user->id);
            } elseif ($user->isOrganization()) {
                $query->where('creator_id', $user->id);
            }
        })->handleFilters()->orderBy('updated_at', 'desc')->get()->map(function($webinar){
            return $webinar->brief ;
        });

        return $webinars ;
    }
    public function indexPurchases(){
        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),
    [
        'webinars'=>$this->purchases() 
    ]);
    }

    public function free(Request $request, $id)
    {
        $user =apiAuth();

        $course = Webinar::where('id', $id)
            ->where('status', 'active')
            ->first();
        abort_unless($course,404) ;


        $checkCourseForSale =$course->checkCourseForSale($course, $user);

        if ($checkCourseForSale != 'ok') {
            return apiResponse2(0,$checkCourseForSale, trans('api.course.purchase.'.$checkCourseForSale)) ;
        }

        if (!empty($course->price) and $course->price > 0) {
      return apiResponse2(0,'not_free', trans('api.cart.not_free')) ;

            
        }

        Sale::create([
            'buyer_id' => $user->id,
            'seller_id' => $course->creator_id,
            'webinar_id' => $course->id,
            'type' => Sale::$webinar,
            'payment_method' => Sale::$credit,
            'amount' => 0,
            'total_amount' => 0,
            'created_at' => time(),
        ]);

        return apiResponse2(1,'enrolled', trans('api.webinar.enrolled')) ;

    }

    public function purchases()
    {
        $user = apiAuth();
        $webinarIds = $user->getPurchasedCoursesIds();

        $webinars = Webinar::whereIn('id', $webinarIds)
        ->handleFilters()->orderBy('created_at', 'desc')
        ->orderBy('updated_at', 'desc')
        ->get()->map(function($webinar){
            return   $webinar->brief ;
            
        }) 
        ;
        return $webinars;
 
    }

    public function invitations(Request $request)
    {
        $user = apiAuth();

     $invitedWebinarIds = WebinarPartnerTeacher::where('teacher_id', $user->id)->pluck('webinar_id')->toArray();
        $webinars = Webinar::where('status', 'active')
        ->whereIn('id', $invitedWebinarIds)
        ->handleFilters()
        ->orderBy('updated_at', 'desc')->get()->map(function($webinar){
            return   $webinar->brief ;
        }) ;
        
        return $webinars;
    }

    public function organizations()
    {
        $user = apiAuth();
       // $user=User::find(927) ;
        
        $webinars = Webinar::where('creator_id', $user->organ_id)
        ->where('status', 'active')->handleFilters()
        ->orderBy('created_at', 'desc')
        ->orderBy('updated_at', 'desc')->get()->map(function($webinar){
         return   $webinar->brief ;
        });

        return $webinars  ;
    }
    public function indexOrganizations(){

        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),
        [
            'webinars'=>$this->organizations() 
        ]);
        
    }
    
 
}
