<?php

namespace Continy\Core;

use App\Models\Token;

class Auth
{


    public static function login($user)
    {
        if ($user->id > 0) {
            $_SESSION['USER_ID'] = $user->id;
            $user->setAuthActive();
            return true;
        } else {
            return false;
        }
    }

    public static function logout($user)
    {
        if (isset($_SESSION['USER_ID'])) {
            unset($_SESSION['USER_ID']);
            $user->setAuthInactive();
            return true;
        } else {
            return false;
        }
    }

    public static function addAttempt(string $email)
    {
        $userAttemptName = Config::get('auth/userAttemptModel');
        $userAttempt = new $userAttemptName;
        $userAttempt->email = $email;
        $userAttempt->remote_ip = $_SERVER['REMOTE_ADDR'];
        $userAttempt->create();
    }

    public static function checkAttempts($email)
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $result = MYSQL::query('SELECT COUNT(*) as n FROM userattempts WHERE email = ?', [$email]);
        $n = $result[0]['n'];
        $max = Config::get('auth/maxLoginAttempts');

        if ($n >= $max) {
            $userBlockName = Config::get('auth/blockModel');
            $userBlock = new $userBlockName;
            $userBlock->email = $email;
            $userBlock->remote_ip = $_SERVER['REMOTE_ADDR'];
            $userBlock->create();

            self::unsetAttempts($email);

            return false;
        } else {
            return true;
        }
    }

    public static function unsetAttempts($email)
    {
        MYSQL::query('DELETE FROM userattempts WHERE email = ?', [$email]);
    }

    public static function valid($request)
    {
        $userModel  = Config::get('auth/userModel');
        $tokenModel = Config::get('auth/tokenModel');
        $blockModel = Config::get('auth/blockModel');

        $route = $request->getRoute();

        if (is_object($route)) {
            if (count($route->authUser()) > 0) {
                $user = new $userModel;
                $userblock = new $blockModel;

                return self::validUser($user, $userblock);
            } elseif ($route->authToken()) {
                $token = new $tokenModel($request->input->__authToken);
                return $token->isValid();
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public static function validUser($user, $userblock)
    {

        if ($user->id > 0) {
            if ($userblock->isIP()) {
                $user->logout();
                return false;
            } elseif ($userblock->is($user->email)) {
                $user->logout();
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    public static function ipBlocked()
    {
        $userModel  = Config::get('auth/userModel');
        $blockModel = Config::get('auth/blockModel');

        $userBlock = new $blockModel();

        if ($userBlock->isIP()) {
            $user = new $userModel;
            $user->logout();
            return true;
        } else {
            return false;
        }
    }

    public static function issueToken($user)
    {
        if ($user->id > 0) {
            $hash = self::makeToken();

            $token = new Token();
            $token->value       = $hash;
            $token->user_issued = $user->id;
            $token->ts          = date('Y-m-d H:i:s');
            $token->create();

            unset($token->id);

            return $token;
        } else {
            return false;
        }
    }

    public static function makeToken()
    {
        $token = openssl_random_pseudo_bytes(16);
        $token = bin2hex($token);

        $token = chunk_split($token, 8, '-');
        $token = rtrim($token, '-');

        return $token;
    }

    public static function validToken($token, $userblock)
    {
        $user = $token->getUser();
        if (is_a($user, Config::get('auth/userModel'))) {
            return self::validUser($user, $userblock);
        } else {
            return false;
        }
    }

    public static function blocked($email)
    {
        $blockModel = Config::get('auth/blockModel');
        $userModel  = Config::get('auth/userModel');

        $userblock = new $blockModel;

        $is = ($userblock->isIP() || $userblock->is($email)) ? true : false;
        $userblock->setStatus($is);
        return $is;
    }


    public static function guard(Route $route, Request $request)
    {

        $userModel  = Config::get('auth/userModel');
        $tokenModel = Config::get('auth/tokenModel');
        $blockModel = Config::get('auth/blockModel');

        if ($route->isPublic()) {
            return true;
        } else {
            // check if a user login is allowed
            $authUserRoles = $route->authUser();
            if (count($authUserRoles) > 0) {
                $user = new $userModel();
                if ($user->hasARoleOf($authUserRoles) || $user->hasARoleOf(Config::get('auth/superRoles'))) {
                    return true;
                }
            }

            // check if a token authentication allowed
            $authTokenRoles = $route->authToken();
            if (isset($request->input->__authToken)) {
                if (count($authTokenRoles) > 0) {
                    $token = new $tokenModel($request->input->__authToken);
                    if ($token->isValid()) {
                        $user = $token->getUser();
                        if ($user->hasARoleOf($authTokenRoles) || $user->hasARoleOf(Config::get('auth/superRoles'))) {
                            return true;
                        } else {
                            return false;
                        }
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            }

            return false;
        }
    }
}
