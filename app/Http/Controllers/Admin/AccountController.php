<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Notification};
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.account', ['account'=>$request->user()]);
    }

    public function emailTemplates(Request $request)
    {
        $templates=['submission'=>'Submission received','review'=>'Reviewer invitation','review-completed'=>'Review completed','revision'=>'Revision requested','acceptance'=>'Manuscript accepted','proof'=>'Proof ready for approval','publication'=>'Article published','verification'=>'Verify your email address','reset'=>'Reset your password','account'=>'Account settings changed','contact'=>'Journal enquiry','newsletter'=>'Journal newsletter'];
        if ($request->filled('preview')) {
            $type=$request->string('preview')->toString();
            abort_unless(isset($templates[$type]),404);
            return \App\Services\EmailPresentation::preview($type)->render();
        }
        return view('admin.email-templates',compact('templates'));
    }

    public function update(Request $request)
    {
        abort_if($request->user()->is_local_admin_bypass, 403, 'Use a real administrator account to change login credentials.');
        $data=$request->validate([
            'current_password'=>['required','current_password:web'],
            'email'=>['required','email:rfc','max:254',Rule::unique('users','email')->ignore($request->user()->id)],
            'password'=>['nullable','confirmed',Password::min(9)->mixedCase()->numbers()->symbols()],
        ]);
        $user=User::findOrFail($request->user()->id);
        $oldEmail=$user->email;
        $passwordChanged=filled($data['password']??null);
        $emailChanged=$oldEmail!==$data['email'];
        DB::transaction(function () use($user,$data,$passwordChanged,$emailChanged,$request) {
            $user->email=$data['email'];
            if($passwordChanged) $user->password=$data['password'];
            if($passwordChanged||$emailChanged) {
                $user->remember_token=null;
                DB::table('sessions')->where('user_id',$user->id)->where('id','!=',$request->session()->getId())->delete();
            }
            $user->save();
        });
        if($emailChanged||$passwordChanged) {
            foreach(array_unique([$oldEmail,$user->email]) as $address) {
                Notification::route('mail',$address)->notify(new \App\Notifications\AccountUpdatedNotification($emailChanged,$passwordChanged));
            }
        }
        return back()->with('success','Your account settings have been saved. Use your updated credentials next time you sign in.');
    }
}
