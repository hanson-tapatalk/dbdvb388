<?php

interface TTSSOForumInterface
{
    // return user info array, including key 'email', 'id', etc.
    public function getUserByEmail($email);
    public function getUserByName($username);

    // the response should be bool to indicate if the username meet the forum requirement
    public function validateUsernameHandle($username);

    // the response should be bool to indicate if the password meet the forum requirement
    public function validatePasswordHandle($password);

    // create a user, $verified indicate if it need user activation
    public function createUserHandle($email, $username, $password, $verified, $custom_register_fields, $profile, &$errors);

    // login to an existing user, return result as bool
    public function loginUserHandle($userInfo, $register);

    // return forum api key
    public function getAPIKey();

    // return forum url
    public function getForumUrl();

    // email obtain from userInfo for compared with TTEmail
    public function getEmailByUserInfo($userInfo);
}

class TTSSOBase
{
    public $result      = false;
    public $register    = 0;
    public $verified    = false;
    public $TTEmail     = '';//note: this email has been convert to lowercase
    public $TTProfile   = array();
    public $userInfo    = array();
    public $errors      = array();
    public $pwdLen      = 8;
    public $status      = 0;
    public $forumInterface;

    public function __construct($forumInterface){
        if (!($forumInterface instanceof TTSSOForumInterface)) {
            return null;
        }
        $this->forumInterface = $forumInterface;
    }

    public function signIn($params)
    {
        $token    = isset($params['token'])? $params['token'] : (isset($params[0]) ? $params[0] : '');
        $code     = isset($params['code'])? $params['code'] : (isset($params[1]) ? $params[1] : '');
        $email    = isset($params['email'])? $params['email'] : (isset($params[2]) ? strtolower($params[2]) : '');
        $username = isset($params['username'])? $params['username'] : (isset($params[3]) ? $params[3] : '');
        $password = isset($params['password'])? $params['password'] : (isset($params[4]) ? $params[4] : '');
        $custom_register_fields  = isset($params['custom_register_fields'])? $params['custom_register_fields'] : (isset($params[5]) ? $params[5] : '');
        $check_spam = isset($params['check_spam']) ? $params['check_spam'] : false;

        $this->setUserInfo($email, $username);
        $this->TTVerify($token, $code);

        // can not get user info from provided parameters, so it's a register
        if (empty($this->userInfo))
        {
            $this->register = 1;
            $this->createUser($email, $username, $password, $custom_register_fields, $check_spam);
            $this->loginUser();
        }
        // user exists, then login
        else if ($this->verified)
        {
            if (strtolower($this->forumInterface->getEmailByUserInfo($this->userInfo)) == $this->TTEmail)
            {
                $this->loginUser();
            }
            else
            {
                $this->errors[] = 'Provided email does not match Tapatalk ID account email for login';
            }
        }
        else
        {
            $this->errors[] = 'Tapatalk authorization verify failed, please login with your username and password.';
        }
    }

    public function createUser($email, $username, $password, $custom_register_fields, $check_spam = false)
    {

        if (empty($email) && empty($this->TTEmail))
        {
            $this->status = 2;
            $this->errors[] = 'Email not provided for registration';
        }
        else if ($email && $this->TTEmail && $email != $this->TTEmail)
        {
            $this->status = 3;
            $this->errors[] = 'Provided email does not match Tapatalk ID account email for registration';
        }
        else if (empty($username))
        {
            $this->status = 2;
            $this->errors[] = 'Username not provided for registration';
        }
        else
        {
            $reg_email = $email ? $email : $this->TTEmail;
            if(!$this->verified && $check_spam)
            {
                $connection = new classTTConnection();
                if($connection->checkSpam($reg_email))
                {
                    $this->errors[] = 'Your email or IP address matches that of a known spammer and therefore you cannot register here. If you feel this is an error, please contact the administrator or try again later.';
                }
            }
            else 
            {
                $username = $this->validateUsername($username);
                $password = $this->validatePassword($password);        
                $this->userInfo = $this->forumInterface->createUserHandle($reg_email, $username, $password, $this->verified, $custom_register_fields, $this->TTProfile, $this->errors);
            }           
        }
    }

    public function loginUser()
    {
        if ($this->userInfo)
        {
            $this->result = $this->forumInterface->loginUserHandle($this->userInfo, $this->register);
        }
    }

    public function setUserInfo($email, $username)
    {
        if ($email){
            $this->userInfo = $this->forumInterface->getUserByEmail($email);
        }else if ($username){
            $this->userInfo = $this->forumInterface->getUserByName($username);
        }
    }

    public function TTVerify($token, $code)
    {
        if ($token && $code)
        {
            if (empty($this->userInfo))
            {
                $this->register = 1;
            }
            $connection = new classTTConnection();
            $verifyResult = $connection->signinVerify($token, $code, $this->forumInterface->getForumUrl(), $this->forumInterface->getAPIKey(), $this->register);

            // get valid response
            if ($connection->success && !empty($verifyResult))
            {
                // pass verify. can register without user activate or login without password
                if (isset($verifyResult['result']) && $verifyResult['result'] && isset($verifyResult['email']) && $verifyResult['email'])
                {
                    $this->verified = true;
                    $this->TTEmail = strtolower($verifyResult['email']);
                    $this->TTProfile = isset($verifyResult['profile']) ? $verifyResult['profile'] : array();
                }
                else if (isset($verifyResult['result_text']) && $verifyResult['result_text'])
                {
                    $this->errors[] = $verifyResult['result_text'];
                }
            }
            else
            {
                if($connection->success == false)
                {
                    $this->errors = $connection->errors;
                }
                $this->errors[] = 'Tapatalk authorization verify with no response';
            }
        }
        else
        {
            $this->errors[] = 'Invalid Tapatalk authorization data';
        }
    }

    public function validateUsername($username)
    {
        if ($this->forumInterface->validateUsernameHandle($username)){
            return $username;
        }else{
            return $this->generateUsername($username);
        }
    }

    public function generateUsername($username)
    {
        for ($i = 1; $i<=3; $i++){
            if($this->forumInterface->validateUsernameHandle($username.sprintf('%02s', $i))){
                return $username.sprintf('%02s', $i);
            }
        }
        $this->status = 1;
        return $username;
    }

    public function validatePassword($password = '')
    {
        if ($this->forumInterface->validatePasswordHandle($password)){
            return $password;
        }else{
            return $this->generatePassword();
        }
    }

    public function generatePassword()
    {
        $str = '';
        if(is_callable('openssl_random_pseudo_bytes')){
            $str = openssl_random_pseudo_bytes(255);
        }else{
            for($i = 0; $i < 256; $i++) $str .= chr(mt_rand(0,255));
        }
        return substr(preg_replace('/[^a-zA-Z0-9]/', '', base64_encode($str)), 0, $this->pwdLen);
    }

    public function setPasswordLength($pwdLen = 8)
    {
        if ($pwdLen >=3 ) $this->pwdLen = intval($pwdLen);
    }
}