<?php
namespace App\Models\Api ;
use App\Models\File as WebFile;

class File extends WebFile {
    
    public function getDetailsAttribute(){
        return [
            'id'=>$this->id ,
            'title'=>$this->title ,
            'auth_has_read'=>$this->read,
            'status'=>$this->status ,
            'order'=>$this->order ,
            'downloadable'=>$this->downloadable ,
            'accessibility'=>$this->accessibility , 
            'description'=>$this->description ,
            'storage'=>$this->storage ,
            'download_link'=>$this->webinar->getUrl().'/file/'.$this->id .'/download' , 
            'auth_has_access'=>$this->auth_has_access ,
             
            'file' => $this->storage == 'local' ? url("/course/".$this->webinar->slug."/file/".$this->id."/play") : $this->file,

            'volume'=>$this->volume ,
            'storage_service'=>$this->getFileStorageService() ,
            'file_type'=>$this->file_type ,
            'created_at'=>$this->created_at ,   
            'updated_at'=>$this->updated_at ,
        ] ;



    }

    public function getAuthHasAccessAttribute(){
      
        $user=apiAuth() ;
        $canAccess=null ;
        if($user){

            $canAccess = true;
            if ($this->accessibility == 'paid') {
                $canAccess = ($this->webinar->checkUserHasBought($user))?true:false;
            }

        }
       

        return $canAccess ;

        
    }

    public function webinar()
    {
        return $this->belongsTo('App\Models\Api\Webinar', 'webinar_id', 'id');
    }
    public function getReadAttribute(){
        $user=apiAuth();
        if(!$user){
            return null ;
        }
        
        return ($this->learningStatus()->where('user_id',$user->id)->count())?true:false ;



    }
}