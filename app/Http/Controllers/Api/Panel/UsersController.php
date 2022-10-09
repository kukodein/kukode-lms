<?php

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Api\Objects\UserObj;
use App\Models\Category;
use App\Models\Newsletter;

use App\Models\UserMeta;
use App\Models\Follow;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

use App\Http\Controllers\Api\UploadFileManager ;


class UsersController extends Controller

{

    public function setting()
    {
        $user = apiAuth();
        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'),
         [
            'user'=>$user->details 
         ] 
        );


    }

    public function updateImages(Request $request){
        $user = apiAuth();
       // dd($request->all()) ;

        if ($request->file('profile_image')){

           
         
            $profileImage = $this->createImage($user, $request->file('profile_image'));
            //$updateData['avatar'] = $profileImage;
            $user->update([
                'avatar' => $profileImage
            ]);

        }

       
        if ($request->file('identity_scan')){ 
            
            $storage=new UploadFileManager($request->file('identity_scan')) ;

            $user->update([
                'identity_scan' => $storage->storage_path  ,
            ]);

        }

        if ($request->file('certificate')){

            $storage=new UploadFileManager($request->file('certificate')) ;

            $user->update([
                'certificate' => $storage->storage_path  ,
            ]);
     

        }

        return apiResponse2(1, 'updated', trans('api.public.updated'));


    }

    public function update(Request $request)
    {
        $available_inputs = [
            'full_name', 'language', 'email', 'password', 'mobile', 'newsletter', 'referral_code'
            , 'public_message', 'profile_image'
        ];
    
        $user = apiAuth();

        

        validateParam($request->all(), [
            'full_name' => 'string',
            'language' => 'string',
            'email' => 'email|unique:users,email,' . $user->id,
            'password' => 'confirmed|min:6',
            'mobile' => 'numeric|unique:users,mobile,' . $user->id,
            'newsletter' => 'boolean',
            'public_message' => 'boolean',
            'referral_code' => '',
            'account_type'=>Rule::in(getOfflineBanksTitle()) ,
            'iban'=>'string' ,
            'account_id'=>'string' ,
            'address'=>'string' ,

            

        ]);
         $user = User::find($user->id);

    
      

        if ($request->input('full_name')) {
           
            $user->update([
                'full_name' => $request->input('full_name')
            ]);
        }

        if ($request->input('language')) {
            $user->update([
                'language' => $request->input('language')
            ]);
        }

        if ($request->input('email')) {
            $user->update([
                'email' => $request->input('email')
            ]);
        }

        if ($request->input('password')) {
            $user->update([
                'password' => User::generatePassword($request->input('password'))
            ]);
        }

        if (is_bool($request->input('newsletter'))) {
            $user->update([
                'newsletter' => $request->input('newsletter')
            ]);
        }

        if (is_bool($request->input('public_message'))) {
            $user->update([
                'public_message' => $request->input('public_message')
            ]);

        }

        if ($request->input('account_type')) {
            $user->update([
                'account_type' => $request->input('account_type')
            ]);

        }
        if ($request->input('iban')) {
            $user->update([
                'iban' => $request->input('iban')
            ]);

        }

        if ($request->input('account_id')) {
            $user->update([
                'account_id' => $request->input('account_id')
            ]);

        }
        if ($request->input('address')) {
            
            $user->update([
                'address' => $request->input('address')
            ]);

        }

        

        return apiResponse2(1, 'updated', trans('api.public.updated'));
    }


    public function updatePassword(Request $request)
    {
        validateParam($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:6',
        ]);
     
        $user = apiAuth();
        if (Hash::check($request->input('current_password'), $user->password)) {
            $user->update([
                'password' => Hash::make($request->input('new_password'))
            ]);
          $token=  auth('api')->refresh();

            return apiResponse2(1, 'updated', trans('api.public.updated'),[
                'token'=> $token 
            ]);

        }
        return apiResponse2(0, 'incorrect', trans('api.public.profile_setting.incorrect'));


    }

    

    private function handleNewsletter($email, $user_id, $joinNewsletter)
    {
        $check = Newsletter::where('email', $email)->first();

        if ($joinNewsletter) {
            if (empty($check)) {
                Newsletter::create([
                    'user_id' => $user_id,
                    'email' => $email,
                    'created_at' => time()
                ]);
            } else {
                $check->update([
                    'user_id' => $user_id,
                ]);
            }
        } elseif (!empty($check)) {
            $check->delete();
        }
    }

    public function followToggle(Request $request, $id)
    {
       // dd('ff') ;
        $authUser = apiAuth();
        validateParam($request->all(),[
            'status'=>'required|boolean'
        ]) ;

        $status=$request->input('status') ;

        $user = User::where('id', $id)->first();
        if(!$user){
            abort(404) ;
        }
        $followStatus = false;
        $follow = Follow::where('follower', $authUser->id)
            ->where('user_id', $user->id)
            ->first();

            if($status){

                if (empty($follow)) {
                    Follow::create([
                        'follower' => $authUser->id,
                        'user_id' => $user->id,
                        'status' => Follow::$accepted,
                    ]);
        
                    $followStatus = true;
        
                } 
                return apiResponse2(1, 'followed', trans('api.user.followed'));
         

            }

            if (!empty($follow)) {
               
                $follow->delete();
                return apiResponse2(1, 'unfollowed', trans('api.user.unfollowed'));

            } 

            return apiResponse2(0, 'not_followed', trans('api.user.not_followed'));

       

         
    }

    public function createImage($user, $img)
    {
        $folderPath = "/" . $user->id . '/avatar';

   //     $image_parts = explode(";base64,", $img);
     //   $image_type_aux = explode("image/", $image_parts[0]);
     //   $image_type = $image_type_aux[1];
      //  $image_base64 = base64_decode($image_parts[1]);
       // $file = uniqid() . '.' . $image_type;

       $file=uniqid().'.'.$img->getClientOriginalExtension() ;
       $storage_path= $img->storeAs($folderPath, $file);
       return  'store/'. $storage_path ;

    //    Storage::disk('public')->put($folderPath . $file, $img);

      //  return Storage::disk('public')->url($folderPath . $file);
    }

    public function storeMetas(Request $request)
    {
        $data = $request->all();

        if (!empty($data['name']) and !empty($data['value'])) {

            if (!empty($data['user_id'])) {
                $organization = auth()->user();
                $user = User::where('id', $data['user_id'])
                    ->where('organ_id', $organization->id)
                    ->first();
            } else {
                $user = auth()->user();
            }

            UserMeta::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'value' => $data['value'],
            ]);

            return response()->json([
                'code' => 200
            ], 200);
        }

        return response()->json([], 422);
    }

    public function updateMeta(Request $request, $meta_id)
    {
        $data = $request->all();
        $user = auth()->user();

        if (!empty($data['user_id'])) {
            $checkUser = User::find($data['user_id']);

            if ((!empty($checkUser) and ($data['user_id'] == $user->id) or $checkUser->organ_id == $user->id)) {
                $meta = UserMeta::where('id', $meta_id)
                    ->where('user_id', $data['user_id'])
                    ->where('name', $data['name'])
                    ->first();

                if (!empty($meta)) {
                    $meta->update([
                        'value' => $data['value']
                    ]);

                    return response()->json([
                        'code' => 200
                    ], 200);
                }

                return response()->json([
                    'code' => 403
                ], 200);
            }
        }

        return response()->json([], 422);
    }

    public function deleteMeta(Request $request, $meta_id)
    {
        $data = $request->all();
        $user = auth()->user();

        if (!empty($data['user_id'])) {
            $checkUser = User::find($data['user_id']);

            if (!empty($checkUser) and ($data['user_id'] == $user->id or $checkUser->organ_id == $user->id)) {
                $meta = UserMeta::where('id', $meta_id)
                    ->where('user_id', $data['user_id'])
                    ->first();

                $meta->delete();

                return response()->json([
                    'code' => 200
                ], 200);
            }
        }

        return response()->json([], 422);
    }

    

    public function search(Request $request)
    {
        $term = $request->get('term');
        $option = $request->get('option', null);
        $user = auth()->user();

        if (!empty($term)) {
            $query = User::select('id', 'full_name')
                ->where(function ($query) use ($term) {
                    $query->where('full_name', 'like', '%' . $term . '%');
                    $query->orWhere('email', 'like', '%' . $term . '%');
                    $query->orWhere('mobile', 'like', '%' . $term . '%');
                })
                ->where('id', '<>', $user->id)
                ->whereNotIn('role_name', ['admin']);

            if (!empty($option) and $option == 'just_teachers') {
                $query->where('role_name', 'teacher');
            }

            $users = $query->get();

            return response()->json($users, 200);
        }

        return response('', 422);
    }

    public function contactInfo(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required'
        ]);

        $user = User::find($request->get('user_id'));

        if (!empty($user)) {
            return response()->json([
                'code' => 200,
                'avatar' => $user->getAvatar(),
                'name' => $user->full_name,
                'email' => !empty($user->email) ? $user->email : '-',
                'phone' => !empty($user->mobile) ? $user->mobile : '-'
            ], 200);
        }

        return response()->json([], 422);
    }

    public function offlineToggle(Request $request)
    {
        $user = auth()->user();

        $message = $request->get('message');
        $toggle = $request->get('toggle');
        $toggle = (!empty($toggle) and $toggle == 'true');

        $user->offline = $toggle;
        $user->offline_message = $message;

        $user->save();

        return response()->json([
            'code' => 200
        ], 200);
    }
}
