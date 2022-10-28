<?php
namespace GenerCodeSlim;

use \Dflydev\FigCookies\FigRequestCookies;
use \Dflydev\FigCookies\FigResponseCookies;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Slim\Handlers\Strategies\RequestHandler;
use \Slim\Exception\HttpUnauthorizedException;

use \Illuminate\Container\Container;

class TokenHandler
{
    private $refresh_minutes = 86400;
    private $auth_minutes = 120;
    private $encoding = 'HS512';
    private $jwt_key;


    function __construct(Container $app) {
        $this->setConfigs($app->config->token);
    }

    public function __get($key)
    {
        if (property_exists($this, $key)) {
            return $this->$key;
        }
    }

    public function setConfigs($configs)
    {
        $arr = ["expire_minutes", "refresh_minutes", "jwt_key", "encoding"];
        foreach ($arr as $key) {
            if (isset($configs[$key])) {
                $this->$key = $configs[$key];
            }
        }
    }

    public function encode(array $payload, int $lifetime_minutes)
    {
        $issuedAt   = new \DateTimeImmutable();
        $expire     = $issuedAt->modify('+' . $lifetime_minutes . ' minutes')->getTimestamp();
        $payload = array_merge([
            'iat'   => $issuedAt->getTimestamp(),         // Issued at: time when the token was generated
            'iss'   => $_SERVER['SERVER_NAME'],                       // Issuer
            'nbf'   => $issuedAt->getTimestamp(),         // Not before
            'exp'   => $expire],                           // Expire
            $payload);
        return JWT::encode($payload, $this->jwt_key, $this->encoding);
    }


    public function decode($token)
    {
        $payload = JWT::decode($token, new Key($this->jwt_key, $this->encoding));
        if (!property_exists($payload, "u")) {
            throw new Exceptions\TokenPayloadException();
        } 

        $now = new \DateTimeImmutable();

        if ($payload->iss !== $_SERVER['SERVER_NAME'] ||
            $payload->nbf > $now->getTimestamp() ||
            $payload->exp < $now->getTimestamp()) {
            throw new \Firebase\JWT\ExpiredException();
        }

        return $payload;
    }



    public function createCookie($name, $value, $expires)
    {
        return \Dflydev\FigCookies\SetCookie::create($name)
        ->withValue($value)
        ->withExpires($expires)
        ->withPath('/')
        ->withSecure(true)
        ->withHttpOnly(true)
        ->withSameSite(\Dflydev\FigCookies\Modifier\SameSite::none());
    }



    public function save($response, string $profile_type, int $id)
    {
        $payload = ["u"=>$profile_type, "i"=>$id];
        $access_token = $this->encode($payload, $this->auth_minutes);
        $refresh_token = $this->encode($payload, $this->refresh_minutes);

        $cookie_expires = time() + 86400; //24 hours update

        $cookies = [];
        $cookies[] = $this->createCookie("api-auth", $access_token, $cookie_expires);
        $cookies[] = $this->createCookie("api-refresh", $refresh_token, $cookie_expires);


        $set = new \Dflydev\FigCookies\SetCookies($cookies);
        $response = $set->renderIntoSetCookieHeader($response);
       
        return $response;
    }


    public function switchTokens($request, $response)
    {
        $refresh = FigRequestCookies::get($request, "api-refresh");
        $auth = FigRequestCookies::get($request, "api-auth");

        if (!$refresh->getValue()) return $response;
        $payload = $this->decode($refresh->getValue());
        $access_token = $this->encode(["u"=>$payload->u, "i"=>$payload->i], $this->auth_minutes);

        $cookie_expires = time() + 86400; //24 hours update
        $cookies = [];
        $cookies[] = $this->createCookie("api-auth", $access_token, $cookie_expires);

        $set = new \Dflydev\FigCookies\SetCookies($cookies);
        $response = $set->renderIntoSetCookieHeader($response);
        return $response;
        //} else {
        //    return $this->logout($response);
        //}
    }

    public function loginFromToken($token, $response, $profile) {
        $payload = $this->decode($token);
        if ($payload->u == $profile) {
            $cookie_expires = time() + 86400; //24 hours update
            $cookies = [];

            $access_token = $this->encode(["u"=>$payload->u, "i"=>$payload->i], $this->auth_minutes);
            $refresh_token = $this->encode(["u"=>$payload->u, "i"=>$payload->i], $this->refresh_minutes);

            $cookies[] = $this->createCookie("api-auth", $access_token, $cookie_expires);
            $cookies[] = $this->createCookie("api-refresh", $refresh_token, $cookie_expires);

            $set = new \Dflydev\FigCookies\SetCookies($cookies);
            $response = $set->renderIntoSetCookieHeader($response);
            $response->getBody()->write(json_encode(true));
        } else {
            $response->getBody()->write(json_encode(false));
        }
        return $response;
    }


    public function logout($response)
    {
        $cookie_expires = 0;
        $cookies = [];
        $cookies[] = $this->createCookie("api-auth", "", $cookie_expires);
        $cookies[] = $this->createCookie("api-refresh", "", $cookie_expires);

        $set = new \Dflydev\FigCookies\SetCookies($cookies);
        $response = $set->renderIntoSetCookieHeader($response);

        return $response;
    }
}