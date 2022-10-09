<?php
namespace App\Models\Api ;
use App\Models\WebinarChapter as WebWebinarChapter;

class WebinarChapter extends WebWebinarChapter {
    

    public function sessions()
    {
        return $this->hasMany('App\Models\Api\Session', 'chapter_id', 'id');
    }

    public function files()
    {
        return $this->hasMany('App\Models\Api\File', 'chapter_id', 'id');
    }

    public function textLessons()
    {
        return $this->hasMany('App\Models\Api\TextLesson', 'chapter_id', 'id');
    }

    public function quizzes()
    {
        return $this->hasMany('App\Models\Api\Quiz', 'chapter_id', 'id');
    }

    public function getDetailsAttribute(){
        return [
            'id'=>$this->id ,
            'title'=>$this->title ,
            'status'=>$this->status ,
            'order'=>$this->order ,
            'type'=>$this->type ,
            'created_at'=>$this->created_at ,
            
            'textLessons'=>$this->textLessons()->get()->map(function($textLesson){
                
             return  $textLesson->details ;
            }) ,
            'sessions'=>$this->sessions()->get()->map(function($sessions){
                return  $sessions->details ;
               }) ,

               'files'=>$this->files()->get()->map(function($file){
                return  $file->details ;
               }) ,

               'quizzes'=>$this->quizzes->map(function($quiz){
                   return $quiz->details ;
               })
            
        ] ;
    }

   

     

    public function getChapterContentAttribute(){
        if($this->type=self::$chapterTextLesson ){
            return $this->textLessons()->get()->map(function($textLesson){
                return  $textLesson->details ;
               }) ;
        }

        if($this->type=self::$chapterFile ){
            return $this->files()->get()->map(function($files){
                return  $files->details ;
               }) ;
        }

        if($this->type=self::$chapterSession ){
            return $this->sessions()->get()->map(function($sessions){
                return  $sessions->details ;
               }) ;
        }

        return null ;

    }
}