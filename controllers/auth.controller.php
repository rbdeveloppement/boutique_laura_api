<?php 

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class AuthController {

public function __construct($params)
{
    $this->method = array_shift($params);
    $this->params = $params;

    $request_body = file_get_contents('php://input');
    $this->body = $request_body ? json_decode($request_body, true) : null;

    $this->action = $this->{$this->method}();
}

public function login(){
    $dbs = new DatabaseService("account");
    $email = filter_var($this->body['login'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ["result" => false];
    }
    $accounts = $dbs->selectWhere("login = ? AND is_deleted = ?", [$email, 0]);
    $prefix = $_ENV['config']->hash->prefix;
    if(count($accounts) == 1
       && password_verify($this->body['password'], $prefix . $accounts[0]->password)){
       $dbs = new DatabaseService("account_user");
       $appUser = $dbs->selectOne($accounts[0]->Id_account_user);
        
        $secretKey = $_ENV['config']->jwt->secret;
        $issuedAt = time();
        $expireAt = $issuedAt + 60 * 60 * 24;
        $serverName = "laura-boutique";
        $userRole = $appUser->Id_role;
        $userId =  $appUser->Id_appUser;
        $requestData = [
            'iat'  => $issuedAt,
            'iss'  => $serverName,
            'nbf'  => $issuedAt,
            'exp'  => $expireAt,
            'userRole' => $userRole,
            'userId' => $userId
        ];
        $token = JWT::encode($requestData, $secretKey, 'HS512');
        return ["result" => true, "role" => $appUser->Id_role, "id" => $appUser->Id_appUser, "token" => $token];
    }
    return ["result" => false];
}

public function check() {
    // $headers = apache_request_headers();
    // if (isset($headers["Authorization"])) {
    //     $token = $headers["Authorization"];
    // }
    if(isset($_COOKIE['laura-boutique'])){
        $token = $_COOKIE['blog'];
    }
    $secretKey = $_ENV['config']->jwt->secret;
    if (isset($token) && !empty($token)) {
        try {
            $payload = JWT::decode($token, new Key($secretKey, 'HS512'));
        } catch (Exception $e) {
            $payload = null;
        }
        if (isset($payload) &&
            $payload->iss === "laura-boutique" &&
            $payload->nbf < time() &&
            $payload->exp > time()) {
           
            return ["result" => true, "role" => $payload->userRole, "id" => $payload->userId];
        }
    }
    return ["result" => false];
}

public function register(){
    $dbs = new DatabaseService("account");
    $accounts = $dbs->selectWhere("login = ?", [$this->body['email']]);
    if(count($accounts) > 0){
        return ['result'=>false, 'message'=>'email '.$this->body['email'].' email deja utilisé'];
    }
    $dbs = new DatabaseService("appUser");
    $users = $dbs->selectWhere("pseudo = ?", [$this->body['pseudo']]);
    if(count($users) > 0){
        return ['result'=>false, 'message'=>'pseudo '.$this->body['pseudo'].' already used'];
    }

    $secretKey = $_ENV['config']->jwt->secret;
    $issuedAt = time();
    $expireAt = $issuedAt + 60 * 60 * 1;
    $serverName = "laura-boutique";
    $pseudo = $this->body['pseudo'];
    $login =  $this->body['email'];
    $requestData = [
        'iat'  => $issuedAt,
        'iss'  => $serverName,
        'nbf'  => $issuedAt,
        'exp'  => $expireAt,
        'pseudo' => $pseudo,
        'login' => $login
    ];
    $token = JWT::encode($requestData, $secretKey, 'HS512');
    
    $href = "http://localhost:3000/account/validate/$token";

    $ms = new MailerService();
    $mailParams = [
        "fromAddress" => ["register@monblog.com","nouveau compte monblog.com"],
        "destAddresses" => [$login],
        "replyAddress" => ["noreply@monblog.com", "No Reply"],
        "subject" => "Créer votre compte nomblog.com",
        "body" => 'Click to validate the account creation <br>
                    <a href="'.$href.'">Valider</a> ',
        "altBody" => "Go to $href to validate the account creation"
    ];
    $sent = $ms->send($mailParams);
    return ['result'=>$sent['result'], 'message'=> $sent['result'] ?
        "Vérifier votre boîte mail et confirmer la création de votre compte sur monblog.com" :
        "Une erreur est survenue, veuiller recommencer l'inscription"];
}

public function validate(){
    $token = $this->body['token'] ?? "";
        
    if(isset($token) && !empty($token)){
        $secretKey = $_ENV['config']->jwt->secret;
        try{
            $payload = JWT::decode($token, new Key($secretKey, 'HS512'));
        }catch(Exception $e){
            $payload = null;
        }
        if (isset($payload) &&
                $payload->iss === "blog.api" &&
                $payload->nbf < time() &&
                $payload->exp > time())
        {
            $pseudo = $payload->pseudo;
            $login =  $payload->login;
            return ["result"=>true, "pseudo"=>$pseudo, "login"=>$login];
        }
    }
    return ['result'=>false];
}

public function create(){
    $dbs = new DatabaseService("appUSer");
    $user = $dbs->insertOne(["pseudo"=>$this->body["pseudo"], "is_deleted"=>0, "Id_role"=>2]);
    if($user){
        
        $password = password_hash($this->body["pass"], PASSWORD_ARGON2ID, [
            'memory_cost' => 1024,
            'time_cost' => 2,
            'threads' => 2
        ]);
        $prefix = $_ENV['config']->hash->prefix;
        $password = str_replace($prefix, "", $password);

        $dbs = new DatabaseService("account");
        $account = $dbs->insertOne(
            ["login"=>$this->body["login"],
            "is_deleted"=>0,
            "password"=>$password,
            "Id_appUser"=> $user->Id_appUser ]);
        if($account){
            return ["result"=>true];
        }
    }
    return ["result"=>false];
}

}