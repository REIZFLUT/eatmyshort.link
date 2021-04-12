<?php

namespace App\Http;

use App\Models\Mail;
use App\Models\PasswordReset;
use Continy\Core\Request;
use Continy\Core\Validation;
use Continy\Core\Auth;
use App\Models\User;
use App\Models\UserBlock;
use Continy\Core\Language;

class LoginController
{

    public function index(Request $request)
    {
        if (Auth::valid($request)) {
            return redirect();
        } else {
            return view('default', 'Login/login');
        }
    }

    public function login(Request $request)
    {

        if (!Auth::ipBlocked()) {

            $email = $request->iVal('email', 'email');
            $passw = $request->iVal('password');

            $validator = new Validation(
                [
                    'email'    => ['email', 'required'],
                    'password' => ['required']
                ]
            );

            if ($validator->check($request)) {

                $user = new User(false);
                $user->findByEmail($email);

                if ($user->id > 0) {
                    if (!Auth::blocked($email)) {
                        if ($user->verifyPwd($passw, $user->passwd)) {
                            Auth::logIn($user);
                            Auth::unsetAttempts($user->email);
                            redirect('/shortlink');
                        } else {
                            Auth::addAttempt($email);
                            Auth::checkAttempts($email);
                            redirect('/login', ['loginFail' => Language::get('login/error/failed')], ['email' => $email]);
                        }
                    } else {
                        redirect('/login', ['loginFail' => UserBlock::msg()], ['email' => $email]);
                    }
                } else {
                    Auth::addAttempt($email); //user not found
                    Auth::checkAttempts($email);
                    redirect('/login', ['loginFail' => Language::get('login/error/failed')], ['email' => $email]);
                }
            } else {
                Auth::addAttempt($email); //user not found
                Auth::checkAttempts($email);
                redirect('/login', $validator->getErrorMessages(), ['email' => $email]);
            }
        } else {
            redirect('/login', ['loginFail' => UserBlock::msg()]);
        }
    }

    public function forgotPwd(Request $request)
    {
        PasswordReset::cleanUp();
        return view('default', 'Login/forgot');
    }

    public function sendPasswordLink(Request $request)
    {
        $email = $request->iVal('email', 'email');
        $validator = new Validation(['email' => ['email', 'required']]);

        if ($validator->check($request)) {

            $user = new User(false);
            $user->findByEmail($email);

            if ($user->id > 0) {

                $resetPassword = new PasswordReset();
                $resetPassword->autoCreate($user->email);

                $mail = new Mail();
                $mail->to($user->email, trim($user->firstname . ' ' . $user->lastname));
                $mail->subject(Language::get('login/forgotmail/subject'));

                $mailBody = view('mail', 'Mail/forgotpassword', ['reset' => $resetPassword], true);
                $textBody = text('Mail/forgotpassword', ['reset' => $resetPassword], false);
                $mail->htmlBody($mailBody);
                $mail->textBody($textBody);
                $mail->send();

                redirect('/login/forgot', ['success' => Language::get('login/success/resetMailSent')], ['email' => $email]);
            } else {
                redirect('/login/forgot', ['fail' => Language::get('login/error/unknownEmail')], ['email' => $email]);
            }
        } else {
            redirect('/login/forgot', $validator->getErrorMessages(), ['email' => $email]);
        }
    }

    public function resetPwd(Request $request)
    {
        PasswordReset::cleanUp();
        return view('default', 'Login/reset');
    }

    public function resetPwdUpdate(Request $request){
        PasswordReset::cleanUp();
        $email = $request->iVal('email', 'email');
        $token = $request->iVal('token');

        

        if(PasswordReset::valid($email, $token)){

            if(User::exists($email)){
                $user = new User(false);
                $user->findByEmail($email);

                if ($request->input->password_new == $request->input->password_cfm) {

                    $valid = new Validation([
                        'email'        => ['required'],
                        'password_new' => ['required', 'secure_password'],
                        'password_cfm' => ['required']
                    ]);
    
                    if ($valid->check($request)) {
                        $user->setNewPwd($request->input->password_new);
                        $user->update();
                        PasswordReset::remove($email);
                        return redirect('/login', ['success' => 'User successfully updated']);
                    } else {
                        return redirect('/login/reset?token='.$token.'&email='.$email, $valid->getErrorMessages());
                    }
                } else {
                    return redirect('/login/reset?token='.$token.'&email='.$email, ['fail' => 'Password confirmation failed']);
                }

            } else {
                return redirect('/login/reset?token='.$token.'&email='.$email, ['fail' => 'User does not exist.']);
            }

        } else {
            return redirect('/login/reset', ['fail' => 'Invalid token.']);
        }


            

            
        




    }


    public function logout()
    {
        $user = new User();
        Auth::logout($user);
        redirect('/login');
    }
}
