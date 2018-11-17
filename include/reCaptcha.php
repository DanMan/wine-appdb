<?php

/*
  Google Re-Captcha Class
  by Jeremy Newman <jnewman@codeweavers.com>
*/

class reCaptcha
{
    private $reCaptchaSecret = '';
    public $res = false;
    public function __construct ($secret = '')
    {
        $this->reCaptchaSecret = $secret;
        return true;
    }
    public function validate ($resp, $ip)
    {
        if (empty($resp) or empty($ip))
            return false;
        $post = array('secret' => $this->reCaptchaSecret, 'response' => $resp, 'remoteip' => $ip);
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $res = json_decode(curl_exec($ch));
        $this->res = $res;
        curl_close($ch);
        if (!empty($res->success) and $res->success)
            return true;
        return false;
    }
}

