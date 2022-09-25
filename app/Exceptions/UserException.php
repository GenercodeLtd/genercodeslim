<?php
namespace PressToJamCore\Exceptions;
use \Slim\Exception\HttpException;

class UserException extends HttpException {

    function __construct($code, $message) {
        $this->code = $code;
        $this->message = $message;
        $this->title = "User Authentication Failed";
        $this->description = "User Authentication Failed";
    }

}