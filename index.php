<?php
/**
 * Tomalish.Networks Main webPage
 * © Hsilamot 2020
 * @author Hsilamot <php@hsilamot.com>
 * @license http://creativecommons.org/licenses/by-nc-sa/3.0/
 * @version 9.0.1
 */
require_once('vendor/autoload.php');
use Firebase\JWT as JWT;
use Tomalish\NuCoKe;
use Tomalish\Laira;

spl_autoload_register(function($className) {
	$workdir = '/user/XXXX/new/';
	if (substr($className,0,9)=='Tomalish\\') {
		$lookout = explode('\\',$className);
		unset($lookout[0]);
		if (file_exists($workdir.implode('/',$lookout).'.class.php')) {
			require_once($workdir.implode('/',$lookout).'.class.php');
		}
	}
});

use Tomalish\CDS\User;

$jwt_password = 'LaPoderosaTienda20210406003718';

$nucoke = new NuCoKe(
						array(
							'name'			=>	'La Poderosa Tienda'		/* name of the project */
							,'version'		=>	'1.0'						/* version of the project */
							,'path'			=>	'/user/XXXXXX/new'		/* path of the project */
							,'charset'		=>	'UTF-8'						/* charset of the files */
							,'timezone'		=>	'America/Mexico_City'		/* timezone of the project*/
							,'errorprint'	=>	true 						/* This is a Debug Line*/
							,'db_default'	=>	'lptdb'
							)
					);

$result = $nucoke->db_add('lptdb',array(
											 'socket'		=> '/var/lib/mysql/mysql.sock'
											,'user'			=> 'XXX'
											,'pass'			=> 'XXX'
											,'database'		=> 'XXX'
											,'log_queries'	=> true /* log SQL Queries */
										));

$laira = new Laira();
$laira->version = array(1,0,1);

$HTTP_RAW_POST_DATA = file_get_contents("php://input");
$JSON = json_decode($HTTP_RAW_POST_DATA);

$url = $_SERVER['REQUEST_URI'];

if ($url=='/api/settings') {
	$laira->addPayload('username','Nombre de Usuario');
	$laira->addPayload('loggedin',false);
	$laira->addPayload('nombre','contenido');
	$laira->addPayload('nombre2','contenido');
	$laira->addPayload('roles',array('ACCESS','GUEST','TEST'));
	$laira->end(200);
}

if ($url=='/api/user') {
	$laira->addPayload('user','Usuario');
	$laira->addPayload('loggedin',false);
	$laira->end(200);
}

define('NuCoKe','nucoke');
$user = new User('1000');

if ($url=='/api/login') {
	$username = '';
	if (isset($JSON->loginuser)) {
		$username = $JSON->loginuser;
	}
	$password = '';
	if (isset($JSON->loginpassword)) {
		$password = $JSON->loginpassword;
	}
	$auth_object = new stdClass();
	$auth_object->user = $username;
	$auth_object->pass = $password;
	$result = User::auth($auth_object);
	if ($result) {
		try {
			$user = new User($result);
		} catch (Exception $e) {
			$laira->addMessage('error','Error: '.$e->getMessage());
		}
	} else {
		$laira->addPayload('loggedin',false);
		$laira->end(403);
	}
	$user->logins++;
	$user->login_ts = time();
	$laira->addPayload('User',$user);
	$laira->addPayload('loggedin',true);

	$payload['iss'] = 'https://'.$_SERVER['HTTP_HOST'].'/'; //issuer
	$payload['sub'] = 'APIAuthToken'; //subject
	$payload['aud'] = 'root'; //audience
	$payload['exp'] = time()+86400; //expiration
	$payload['nbf'] = time(); //not before
	$payload['iat'] = time(); //issueat at this time
	$payload['jti'] = $user->ID.'.'.sha1(time().rand(10000,99999));
	$token = JWT\JWT::encode($payload,$jwt_password);

	$laira->addPayload('token',$token);

	$laira->end(200);
}

if ($url=='/api/token') {
	$new_token = '';
	if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
		$bearer = $_SERVER['HTTP_AUTHORIZATION'];
		$bearer = substr($bearer,7);
		try {
			$token = JWT\JWT::decode($bearer, $jwt_password, array('HS256'));
			$laira->addPayload('decodifique',$token);
			if ($token->exp>time()) {
				$get_user = explode('.',$token->jti);
				$get_user = $get_user[0];
				$user = 0;
				try {
					$user = new User($get_user);
				} catch (Exception $e) {
					$laira->addMessage('error','Error: '.$e->getMessage());
				}
				if (is_object($user)) {
					$laira->addPayload('User',$user);
					$laira->addPayload('loggedin',true);
					$payload['iss'] = 'https://'.$_SERVER['HTTP_HOST'].'/'; //issuer
					$payload['sub'] = 'APIAuthToken'; //subject
					$payload['aud'] = 'root'; //audience
					$payload['exp'] = time()+86400; //expiration
					$payload['nbf'] = time(); //not before
					$payload['iat'] = time(); //issueat at this time
					$payload['jti'] = $user->ID.'.'.sha1(time().rand(10000,99999));
					$token = JWT\JWT::encode($payload,$jwt_password);
					$laira->addPayload('token',$token);
					$laira->end(200);
				}
				$laira->addMessage('error','Apparently we could not retrieve the user object!');
			} else {
				$laira->addMessage('warning','Token has expired!');
				$laira->end(401); //Unauthorized
			}
		} catch (Exception $e) {
			$laira->addMessage('error','Error: '.$e->getMessage());
		}
	}
	$laira->addMessage('error','Unexpected error on token call');
	$laira->end(500);
}

if ($url=='/api/test') {
	exit(0);
}

$laira->addMessage('error','Unexpected end');
$laira->end(500);
