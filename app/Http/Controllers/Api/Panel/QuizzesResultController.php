<?php
namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Controller;
use App\Models\Api\Quiz;
use App\Models\Role;
use App\Models\WebinarChapter;
use App\User;
use App\Models\Webinar;
use App\Models\Api\QuizzesResult;
use App\Models\Api\QuizzesQuestion;
use App\Models\Api\QuizzesQuestionsAnswer;
use Doctrine\Inflector\Rules\English\Rules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
 

class QuizzesResultController extends Controller{
    

    public function myResults(Request $request)
    {
        $quizResults=apiAuth()->quizResults()->handleFilters()
        ->orderBy('created_at', 'desc')
            ->get()->map(function($quizResult){
                return $quizResult->details ;
            }) ;

            return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),[
                'results'=>$quizResults
            ]);

            return $quizResults ;
    
    }

    public function myStudentResult(Request $request){

        $user=apiAuth() ;
        $quizzes_id = Quiz::where('creator_id', $user->id)
        ->where('status', 'active')
        ->get()->pluck('id')->toArray();

        $quizResults = QuizzesResult::whereIn('quiz_id', $quizzes_id)->handleFilters()
        ->orderBy('created_at', 'desc')
            ->get()->map(function($quizResult){
                return $quizResult->details ;
            }) ;
            return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),[
                'results'=>$quizResults
            ]);

  return $quizResults ;
    }

    public function myStudentResultGroupByUser(Request $request){

        $user=apiAuth() ;
        $quizzes_id = Quiz::where('creator_id', $user->id)
        ->where('status', 'active')
        ->get()->pluck('id')->toArray();

        $quizResults = QuizzesResult::whereIn('quiz_id', $quizzes_id)->handleFilters()
        ->orderBy('created_at', 'desc')->groupBy('user_id')
            ->get()->map(function($quizResult){
                return $quizResult->details ;
            }) ;
            return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),[
                'results'=>$quizResults
            ]);

  return $quizResults ;
    }

    public function quizzesStoreResult(Request $request, $id)
    {
       // dd($request->all()) ;
        $user =apiAuth();
        $quiz = Quiz::where('id', $id)->first();
        abort_unless($quiz,404) ;
        validateParam($request->all(),[
          'quiz_result_id'=>[
                'required' , Rule::exists('quizzes_results', 'id')->where('user_id', $user->id)
          ],
          'answer_sheet'=>['nullable','array','min:0'] ,

          'answer_sheet.*.question_id'=>['required', Rule::exists('quizzes_questions', 'id')
          ->where('quiz_id', $quiz->id)
          ] ,
          'answer_sheet.*.answer'=> ['nullable', 
          
         // Rule::exists('quizzes_questions_answers', 'id')
          
          ] ,
           
        ]) ;

        $answer_sheet = $request->get('answer_sheet');
        $quizResultId = $request->get('quiz_result_id');

        $quizResult = QuizzesResult::where('id', $quizResultId)
        ->where('user_id', $user->id)
        ->first();

        if(   $quizResult->finished ){
      
            return apiResponse2(0, 'finished', trans('api.quiz.finished'));
      
        }

        $passMark = $quiz->pass_mark;
        $totalMark = 0;
        $status = '';

        $results=[] ;
        if (!empty($answer_sheet)) {
            foreach ($answer_sheet as $k => $result) {

                $questionId=$result['question_id'];
                // $result['answer']
                $user_answer=$result['answer']??null;
                $results[$questionId]['answer'] =$user_answer ;

                if (!is_array($result)) {
                    unset($results[$questionId]);

                } else {
                    

                    $question = QuizzesQuestion::where('id', $questionId)
                        ->where('quiz_id', $quiz->id)
                        ->first();

                        if ($question->type == 'multiple') {
                          validateParam($request->all(),[
                            'answer_sheet.'.$k.'.answer'=> ['nullable', 
                           Rule::exists('quizzes_questions_answers', 'id')]
                          ]) ;
                        }

                    if ($question and !empty($user_answer)) {
                        $answer = QuizzesQuestionsAnswer::where('id',$user_answer)
                            ->where('question_id', $question->id)
                            ->where('creator_id', $quiz->creator_id)
                            ->first();

                        $results[$questionId]['status'] = false;
                        $results[$questionId]['grade'] = $question->grade;

                        if ($answer and $answer->correct) {
                            $results[$questionId]['status'] = true;
                            $totalMark += (int)$question->grade;
                        }

                        if ($question->type == 'descriptive') {
                            $status = 'waiting';
                        }
                    }
                }
            }
        }

        if (empty($status)) {
            $status = ($totalMark >= $passMark) ? QuizzesResult::$passed : QuizzesResult::$failed;
        }

        $attempt_num=QuizzesResult::where('quiz_id', $quiz->id)
        ->where('user_id', $user->id)
        ->count()+1;
        $results["attempt_number"] =  $attempt_num;

        $quizResult->update([
            'results' => json_encode($results),
            'user_grade' => $totalMark,
            'status' => $status,
            'created_at' => time()
        ]);

        if ($quizResult->status == QuizzesResult::$waiting) {
            $notifyOptions = [
                '[c.title]' => $quiz->webinar_title,
                '[student.name]' => $user->full_name,
                '[q.title]' => $quiz->title,
            ];
            sendNotification('waiting_quiz', $notifyOptions, $quiz->creator_id);
        }
        return apiResponse2(1, 'stored', trans('api.public.stored') ,[
            'result'=>$quizResult->details
        ]);

       // return redirect()->route('quiz_status', ['quizResultId' => $quizResult]);
         
    }

    public function storeResult(Request $request, $id)
    {
        $user = apiAuth() ;
        $quiz = Quiz::find($id);

       
        if(!$quiz){
            abort(404) ;

        }
        validateParam($request->all(), [
            'answer_sheet' => 'required|array',
            'answer_sheet.*.question_id'=>['required', Rule::exists('quizzes_questions', 'id')
            ->where('quiz_id', $quiz->id)
            ] ,
            'answer_sheet.*.answer_id'=>
           
            ['required_without:answer_sheet.*.descriptive_answer', 
            Rule::exists('quizzes_questions_answers', 'id')] ,

            'answer_sheet.*.descriptive_answer'=>
       
            'required_without:answer_sheet.*.answer_id' ,

            'quiz_result_id' => ['required',
                Rule::exists('quizzes_results', 'id')->where('user_id', $user->id)
              //  ->where('status',QuizzesResult::$waiting)
            ]
        ]);
        $attempt_num=QuizzesResult::where('quiz_id', $quiz->id)
        ->where('user_id', $user->id)
        ->count();
        
        $answerSheets = $request->input('answer_sheet');
        $quizResultId = $request->input('quiz_result_id');

        $quizResult = QuizzesResult::where('id', $quizResultId)
                ->where('user_id', $user->id)
                ->first();

          if(  !$quizResult->finished ){
      
            return apiResponse2(0, 'finished', trans('api.quiz.finished'));
      
        }
                $passMark = $quiz->pass_mark;
                $totalMark = 0;
                $status = '';

                foreach ($answerSheets as $sheet) {

                    $question_id=$sheet['question_id'] ;
                    $answer_id=$sheet['answer_id']??null ;
                    $descriptive_answer=$sheet['descriptive_answer']??null ;

                    $results[$question_id]['answer'] = $answer_id;

                    $question = QuizzesQuestion::where('id', $question_id)
                    ->where('quiz_id', $quiz->id)
                    ->first();
                   // dd('ff') ;
                    if($question->type=='descriptive'){
                      
                      
                    }else{
                      
                    }


                    $answer = QuizzesQuestionsAnswer::where('id', $answer_id)
                    ->where('question_id', $question->id)
                    ->where('creator_id', $quiz->creator_id)
                    ->first();

                $results[$question_id]['status'] = false;
                $results[$question_id]['grade'] = $question->grade;
               
                if ($answer and $answer->correct) {
                    $results[$question_id]['status'] = true;
                    $totalMark += (int)$question->grade;
                }

                if ($question->type == 'descriptive') {

                    $results[$question_id]['answer'] = $descriptive_answer;

                    $status = 'waiting';
                }
            }


                if (empty($status)) {
                    $status = ($totalMark >= $passMark) ? QuizzesResult::$passed : QuizzesResult::$failed;
                }

                $results["attempt_number"] =$attempt_num ;

                $quizResult->update([
                    'results' => json_encode($results),
                    'user_grade' => $totalMark,
                    'status' => $status,
                    'created_at' => time()
                ]);

                if ($quizResult->status == QuizzesResult::$waiting) {
                    $notifyOptions = [
                        '[c.title]' => $quiz->webinar_title,
                        '[student.name]' => $user->full_name,
                        '[q.title]' => $quiz->title,
                    ];

                    sendNotification('waiting_quiz', $notifyOptions, $quiz->creator_id);
                }

                return apiResponse2(1, 'stored', trans('api.public.stored') ,[
                    'result'=>$quizResult->details
                ]);
               

    
    }

    public function status($quizResultId)
    {
        $user = apiAuth();

        $quizResult = QuizzesResult::where('id', $quizResultId)
            ->where('user_id', $user->id)
            ->first();

            if(!$quizResult){
                abort(404) ;
            }
 
            return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),[
                'result'=>$quizResult->details 
            ]);          
      
    }

    public function start(Request $request, $id)
    {
        $user = apiAuth();
        $quiz = Quiz::find($id) ;

        if (!$quiz) {
            abort(404);
        }

        $userQuizDone = QuizzesResult::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->get();
            
        
        $status_pass = false;
        foreach ($userQuizDone as $result) {
            if ($result->status == QuizzesResult::$passed) {
                $status_pass = true;
            }
        }
 
        if (!isset($quiz->attempt) or ($userQuizDone->count() < $quiz->attempt and !$status_pass)) {
         
            $newQuizStart = QuizzesResult::create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'results' => '',
                'user_grade' => 0,
                'status' => 'waiting',
                'created_at' => time()
            ]);

            return apiResponse2(1, 'stored', trans('api.public.stored'), 
            [
                'quiz_result_id'=>$newQuizStart->id ,
                'quiz' => $quiz->details, 'attempt_number' => $userQuizDone->count() + 1]);
 
            } else {

            if ($status_pass) {
             
                $status = 'passed';
                $msg = trans('api.quiz.passed');
           
            } elseif ($userQuizDone->count() == $quiz->attempt) {
             
                $msg = trans('api.quiz.max_attempt');
                $status = 'max_attempt';

            }

            //$msg = 'User can not start this quiz because ' . $msg;
            return apiResponse2(0, $status, $msg);
        }

    }

    public function updateResult(Request $request, $quizResultId)
    {
        $user = apiAuth();
        $quizResult = QuizzesResult::where('id', $quizResultId)->first() ;
        abort_unless($quizResult,404) ;
        
       

       // dd($quizResult->reviewable);
        if(!$quizResult->reviewable){
     //       return apiResponse2(0, 'unreviewable', trans('api.quiz.retrieved'));
        }

        $quiz= $quizResult->quiz()->where('creator_id', $user->id)->first() ;
        
        abort_unless($quiz,404) ;

           validateParam($request->all(),[
              // 'correction'=>'array|required' ,
               '*.question_id'=>['required', Rule::exists('quizzes_questions', 'id')
               ->where('quiz_id', $quiz->id)->where('type','descriptive')
           ],
               
             //  'correction.*.answer'=>'required' ,
               '*.grade'=>'required' ,

           ]) ;
          // dd($request->all()) ;
           $reviews = $request->get('correction');
           $reviews = $request->all();
           $user_grade = $quizResult->user_grade;
           $oldResults = json_decode($quizResult->results, true);

           if (!empty($oldResults) and count($oldResults)) {
            foreach ($oldResults as $question_id => $result) {

                foreach ($reviews as  $review) {

                    $review_question_id=$review['question_id'] ;
                    $review_answer=$review['answer']  ;
                    $review_grade=$review['grade']  ;


                    if ($review_question_id == $question_id) {
                        $question = QuizzesQuestion::where('id', $question_id)
                            ->where('creator_id', $user->id)
                            ->first();

                        if ($question->type == 'descriptive') {
                            if (!empty($result['status']) and $result['status']) {

                                $user_grade = $user_grade - (isset($result['grade']) ? (int)$result['grade'] : 0);
                                $user_grade = $user_grade + (isset($review_grade) ? (int)$review_grade : (int)$question->grade);
                            } else if (isset($result['status']) and !$result['status']) {

                                $user_grade = $user_grade + (isset($review_grade) ? (int)$review_grade : (int)$question->grade);
                                $oldResults[$question_id]['grade'] = isset($review_grade) ? $review_grade : $question->grade;
                            }

                            $oldResults[$question_id]['status'] = true;
                        }
                    }
                }
            }
        }
        elseif (!empty($reviews) and count($reviews)) {
            foreach ($reviews as  $review) {

                $review_question_id=$review['question_id'] ;
                $review_answer=$review['answer']  ;
                $review_grade=$review['grade']  ;


                $question = QuizzesQuestion::where('id', $review_question_id)
                        ->where('quiz_id', $quiz->id)
                        ->first();

                    if ($question and $question->type == 'descriptive') {
                        $user_grade += (isset($review_grade) ? (int)$review_grade : 0);
                    }
            }

            $oldResults = $reviews;
        }

        $quizResult->user_grade = $user_grade;
        $passMark = $quiz->pass_mark;

        if ($quizResult->user_grade >= $passMark) {
            $quizResult->status = QuizzesResult::$passed;
        } else {
            $quizResult->status = QuizzesResult::$failed;
        }

        $quizResult->results = json_encode($oldResults);

        $quizResult->save();

        $notifyOptions = [
            '[c.title]' => $quiz->webinar_title,
            '[q.title]' => $quiz->title,
            '[q.result]' => $quizResult->status,
        ];
        sendNotification('waiting_quiz_result', $notifyOptions, $quizResult->user_id);

        
        return apiResponse2(1, 'stored', trans('api.public.stored'));
   


    }
 
    


    
    
    



}