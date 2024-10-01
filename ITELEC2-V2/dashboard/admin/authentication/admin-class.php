<?php
require_once __DIR__.'/../../../database/dbConnection.php';
include_once __DIR__.'/../../../config/settings-configuration.php';
require_once __DIR__.'/../../../src/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


class ADMIN
{
    private $conn;
    private $settings;
    private $smtp_email;
    private $smtp_password;

    public function __construct()
    {
        $this->settings = new SystemConfig();
        $this->smtp_email = $this->settings->getSmtpEmail();
        $this->smtp_password = $this->settings->getSmtpPassword();

        $database = new Database();
        $this->conn = $database->dbConnection();
    }

    public function sendOtp($otp, $email){
        if($email == NULL){
            echo "<script>alert('No email found'); window.location.href = '../../../';</script>";
            exit;
        }else{
            $stmt = $this->runQuery("SELECT * FROM user WHERE email = :email");
            $stmt->execute(array(":email" => $email));
            $stmt->fetch(PDO::FETCH_ASSOC);

            if($stmt->rowCount() > 0){
                echo "<script>alert('Email already taken, please try another one'); window.location.href = '../../../';</script>";
            exit;
            }else{
                $_SESSION['OTP'] = $otp;

                $subject = "OTP VERIFICATION";
                $message = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <title>OTP Verifecation</title>
                    <style>
                        body {
                        font-family: Arial, sans-serif;
                        background-color: #f5f5f5;
                        margin: 0;
                        padding: 0;
                        }

                        .container {
                            max-width: 60px;
                            matgin: 0 auto;
                            padding: 30px;
                            background-color: #ffffff;
                            border-raduis: 4px;
                            box-shadown: 0 2px 4px rgba(0, 0, 0, 0.1);
                        }

                        h1 {
                            color: #333333;
                            font-size: 24px;
                            margin-bottom: 20px;
                        }
                        
                        p {
                            color: #666666;
                            font-size: 16px;
                            margin-bottom: 10px;
                        }
                        
                        .button {
                            display: inline-block;
                            padding: 12px 24px;
                            background-color: #0088cc;
                            color: #ffffff;
                            text-decoration: none;
                            border-radius: 4px;
                            font-size: 16px;
                            margin-top: 20px;
                        }
                        
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h1>OTP Verification</h1>
                        <p>Hello.$email</p>
                        <p>Your OTP is: $otp</p>
                        <p>If you didn't request an OTP, please ignore this emai.</p>
                        <p>Thank you!</p>
                    </div>
                </body>
                </html>";

                $this->send_email($email, $message, $subject, $this->smtp_email, $this->smtp_password);
                echo "<script>alert('We sent the OTP to $email'); window.location.href = '../../../verify-otp.php';</script>";
                exit;
            }
        }
    }

    public function addAdmin($csrf_token, $username, $email, $password)
    {
        $stmt = $this->runQuery("SELECT * FROM user WHERE email = :email");
        $stmt->execute(array(":email" => $email));

        if($stmt->rowCount() > 0){
            echo "<script>alert('Email already exists.'); window.location.href = '../../../';</script>";
            exit;
        }

        if(!isset($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)){
            echo "<script>alert('Invalid CSRF token.'); window.location.href = '../../../';</script>";
            exit;
        }

        unset($_SESSION['csrf_token']);

        $hash_password = md5($password);

        $stmt = $this->runQuery("INSERT INTO user (username, email, password) VALUES (:username, :email, :password)");
        $exec = $stmt->execute(array(
            ":username" => $username,
            ":email" => $email,
            ":password" => $hash_password
        ));

        if($exec){
            echo "<script>alert('Admin Added Successfully.'); window.location.href = '../../../';</script>";
            exit;
        }else{
            echo "<script>alert('Error Adding Admin.'); window.location.href = '../../../';</script>";
            exit;
        }
    }

    public function adminSignin($email, $password, $csrf_token){
        try{
            if(!isset($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)){
                echo "<script>alert('Invalid CSRF token.'); window.location.href = '../../';</script>";
                exit;
            }
            unset($_SESSION['csrf_token']);

            $stmt = $this->conn->prepare("SELECT * FROM user WHERE email = :email");
            $stmt->execute(array(":email" => $email));
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if($stmt->rowCount() == 1 && $userRow['password'] == md5($password)){
                $activity = "Has Successfully signed in";
                $user_id = $userRow['id'];
                $this->logs($activity, $user_id);

                $_SESSION['adminSession'] = $user_id;

                echo "<script>alert('Welcome'); window.location.href = '../';</script>";
                exit;
            }else{
                echo "<script>alert('Invalid Credentials'); window.location.href = '../../../';</script>";
                exit;
            }

        }catch(PDOExeption $ex){
            echo $ex->getMessage();
        }
    }

    public function adminSignout()
    {
        unset($_SESSION['adminSession']);
        echo "<script>alert('Sign Out Succesfully'); window.location.href = '../../../';</script>";
        exit;
    }

    function send_email($email, $message, $subject, $smtp_email, $smtp_password){
    $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = "tls";
        $mail->Host = "smtp.gmail.com";
        $mail->Port = 587;
        $mail->addAddress($email);
        $mail->Username = $smtp_email;
        $mail->Password = $smtp_password;
        $mail->setFrom($smtp_email, "Mikaella");
        $mail->Subject = $subject;
        $mail->msgHTML($message);
        $mail->Send();
    }

    public function logs($activity, $user_id)
    {
        $stmt = $this->conn->prepare("INSERT INTO logs (user_id, activity) VALUES (:user_id, :activity)");
        $stmt->execute(array(":user_id" => $user_id, ":activity" => $activity));
    }

    public function isUserLoggedIn()
    {
        if(isset($_SESSION['adminSession'])){
            return true;
        }
    }
    public function redirect()
    {
        echo "<script>alert('Admin must loggin first'); window.location.href = '../../../';</script>";
                exit;
    }

    public function runQuery($sql)
    {
        $stmt = $this->conn->prepare($sql);
        return $stmt;
    }
}

if(isset($_POST['btn-signup'])){
    $_SESSION['not_verify_csrf_token'] = trim($_POST['csrf_token']);
    $_SESSION['not_verify_username'] = trim($_POST['username']);
    $_SESSION['not_verify_email'] = trim($_POST['email']);
    $_SESSION['not_verify_password'] = trim($_POST['password']);

    $email = trim($_POST['email']);
    $otp = rand(100000, 999999);

    $addAdmin = new ADMIN();
    $addAdmin->sendotp($otp, $email);
}

if(isset($_POST['btn-signin'])){
    $csrf_token = trim($_POST['csrf_token']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $adminSignin = new ADMIN();
    $adminSignin->adminSignin($email, $password, $csrf_token);
}

if(isset($_GET['admin_signout'])){

    $admin_Signout = new ADMIN();
    $admin_Signout->adminSignout();
}
?>