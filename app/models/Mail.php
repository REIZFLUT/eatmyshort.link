<?php

namespace App\Models;

use Continy\Core\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mail{
    
    private $mailer;

    public function __construct()
    {

        $this->mailer = new PHPMailer(true);
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->isHTML(false);
        try{
            //$this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;

            if(Config::get('mail/useSMTP')){
                $this->mailer->isSMTP(); 
                $this->mailer->Host       = Config::get('mail/smtp/host');
                $this->mailer->SMTPAuth   = Config::get('mail/smtp/auth');
                $this->mailer->Username   = Config::get('mail/smtp/user');
                $this->mailer->Password   = Config::get('mail/smtp/password');
                $this->mailer->SMTPSecure = Config::get('mail/smtp/encryption') ; 
                $this->mailer->Port       = Config::get('mail/smtp/port');
            }
            $this->mailer->setFrom(Config::get('mail/from'));    
        } catch(Exception $e){
            dd($e);
        }  
        return $this->mailer;    
    }

    public function to(string $email){
        if(filter_var($email, FILTER_VALIDATE_EMAIL)){
            $this->mailer->addAddress($email);
        }
        return $this->mailer;
    }

    public function subject(string $subject){
        $this->mailer->Subject = $subject;
        return $this->mailer;
    }

    public function htmlBody(string $htmlBody){
        $this->mailer->isHTML(true);
        $this->mailer->Body = $htmlBody;
        return $this->mailer;
    }

    public function textBody(string $textBody, bool $isAltBody = true){
        if($isAltBody){
            $this->mailer->AltBody = $textBody;
        } else {
            $this->mailer->Body = $textBody;
        }
        return $this->mailer;
    }

    public function send(){
        try{
            $this->mailer->send();
        } catch(Exception $e){
            dd($e);
        }
    }

}