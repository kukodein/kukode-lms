<?php

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Api\Web\WebinarController;
use App\Models\Api\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Api\Quiz;
use App\Models\Api\QuizzesResult;
use App\Models\Sale;
use App\Models\Api\Webinar;
use App\Models\Api\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class CertificatesController extends Controller
{
    public function created(Request $request)
    {
        $user = apiAuth();
 

    $quizzes=Quiz::where('creator_id', $user->id)
    ->where('status', Quiz::ACTIVE)->handleFilters()->get()->map(function($quiz){
        return $quiz->details ;
    }) ;

    return apiResponse2(1, 'retrieved', trans('public.retrieved'), [
        'quizzes' => $quizzes,
    ]);

   
     }

     public function students()
     {
        $user = apiAuth();
 

    $quizzes=Quiz::where('creator_id', $user->id)->pluck('id')->toArray();


    $ee=  Certificate::whereIn('quiz_id',$quizzes)
        ->get()
        ->map(function($certificate){

         return   $certificate->details ;

        })   ;    

        return apiResponse2(1, 'retrieved', trans('public.retrieved'), $ee);
     }

    public function achievements(Request $request)
    {
        $user = apiAuth();

      $results=  QuizzesResult::where('user_id',$user->id)->where('status',QuizzesResult::$passed)
        ->get()->map(function($result){

            return array_merge($result->details,
            ['certificate'=>$result->certificate->brief??null ]
            ) ;

        }) ;
     
    
   return apiResponse2(1, 'retrieved', trans('public.retrieved'), $results);      
        
        

     }
 
   

    public function download($quizResultId)
    {
        $user = apiAuth();

        $quizResult = QuizzesResult::where('id', $quizResultId)
            ->where('user_id', $user->id)
            ->where('status', QuizzesResult::$passed)
            ->first();

            if(!$quizResult){
      abort(404) ;
            }
            if(!$quizResult->quiz->auth_can_download_certificate){

                return apiResponse2(1, 'not_access', trans('api.certificate.not_access'));
            }

        $makeCertificate = $this->makeCertificate($quizResult);

        $file_path = $this->saveCertificate($makeCertificate);

        if (!empty($file_path)) {
            if (file_exists(public_path($file_path))) {
                return response()->download(public_path($file_path));
            }
        }

        abort(404);
    }

    public function show($quizResultId)
    {
        $user = apiAuth() ;
        $quizResult = QuizzesResult::where('id', $quizResultId)
        ->where('user_id', $user->id)
        ->where('status', QuizzesResult::$passed)
        ->first();
        $makeCertificate = $this->makeCertificate($quizResult);

        $this->saveCertificate($user, $makeCertificate);

        $img = $makeCertificate['img'];

        return $img->response('png');
    }

    private function makeCertificate($quizResult)
    {
        $user = apiAuth();

        if (!empty($quizResult) and !empty($quizResult->quiz)) {

            $certificateTemplate = CertificateTemplate::where('status', 'publish')->first();

            if (!empty($certificateTemplate)) {
                $quiz = $quizResult->quiz;

                $certificate = Certificate::where('quiz_id', $quiz->id)
                    ->where('student_id', $user->id)
                    ->where('quiz_result_id', $quizResult->id)
                    ->first();

                if (empty($certificate)) {
                    $certificate = Certificate::create([
                        'quiz_id' => $quiz->id,
                        'student_id' => $user->id,

                        'quiz_result_id' => $quizResult->id,
                        'created_at' => time()
                    ]);
                }

             //   dd($certificateTemplate->image) ;
                $img = Image::make(public_path($certificateTemplate->image));
              //  $img = Image::make($certificateTemplate->image);
                $body = $certificateTemplate->body;
                $body = str_replace('[student]', $user->full_name, $body);
                $body = str_replace('[course]', $quiz->webinar->title, $body);
                $body = str_replace('[grade]', $quizResult->user_grade, $body);
                $body = str_replace('[certificate_id]', $certificate->id, $body);

                if ($certificateTemplate->rtl) {
                    $Arabic = new \I18N_Arabic('Glyphs');
                    $body = $Arabic->utf8Glyphs($body);
                }

                $img->text($body, $certificateTemplate->position_x, $certificateTemplate->position_y, function ($font) use ($certificateTemplate) {
                    $font->file(public_path('assets/default/fonts/Montserrat-Medium.ttf'));
                    $font->size($certificateTemplate->font_size);
                    $font->color($certificateTemplate->text_color);
                });

                //return $img ;

                return [
                    'img' => $img,
                    'quizResult' => $quizResult,
                    'quiz' => $quiz,
                ];
            }
        }

        abort(404);
    }
    private function saveCertificate( $makeCertificate)
    {
        $quiz = $makeCertificate['quiz'];
        $quizResult = $makeCertificate['quizResult'];
        $img = $makeCertificate['img'];

        $user=apiAuth() ;
        if (!empty($img)) {
            $path = public_path("store/$user->id/certificates");

            if (!is_dir($path)) {
                File::makeDirectory($path, 0777, true);
            }

            $file_path = $path . '/' . $quiz->webinar->title . '(' . $quiz->name . ').jpg';
            if (is_file($file_path)) {
                $file_path = $path . '/' . $quiz->webinar->title . '(' . $quiz->name . '-' . $quizResult->user_grade . ').jpg';
            }

            $img->save($file_path);

            $file_path = '/' . explode('/public/', $file_path)[1];

            $certificate = Certificate::where('quiz_id', $quiz->id)
                ->where('student_id', $user->id)
                ->where('quiz_result_id', $quizResult->id)
                ->first();

            $data = [
                'quiz_id' => $quiz->id,
                'student_id' => $user->id,
                'quiz_result_id' => $quizResult->id,
                'user_grade' => $quizResult->user_grade,
                'file' => $file_path,
                'created_at' => time()
            ];

            if (!empty($certificate)) {
                $certificate->update($data);
            } else {
                Certificate::create($data);

                $notifyOptions = [
                    '[c.title]' => $quiz->webinar_title,
                ];
                sendNotification('new_certificate', $notifyOptions, $user->id);
            }

            return $file_path;
        }

        return null;
    }

   
}
  
