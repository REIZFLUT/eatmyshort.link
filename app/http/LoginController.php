<?php

namespace App\Http;

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
        //TODO: Provide view to enter valid email
        // if valid token provided: do password reset
    }

    public function sendPasswordLink(Request $request)
    {
        //TODO: Send Token via E-Mail
    }

    public function resetPassword()
    {
        //TODO: check token and reset Password
    }

    public function logout()
    {
        $user = new User();
        Auth::logout($user);
        redirect('/login');
    }
}
