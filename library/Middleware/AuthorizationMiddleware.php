<?php

namespace Niden\Middleware;

use function time;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;
use Niden\Exception\Exception;
use Niden\Exception\ModelException;
use Niden\Http\Request;
use Niden\Http\Response;
use Niden\Models\Users;
use Niden\Traits\ResponseTrait;
use Niden\Traits\UserTrait;
use Phalcon\Mvc\Micro;
use Phalcon\Mvc\Micro\MiddlewareInterface;

/**
 * Class AuthenticationMiddleware
 *
 * @package Niden\Middleware
 */
class AuthorizationMiddleware implements MiddlewareInterface
{
    use ResponseTrait;
    use UserTrait;

    /**
     * @param Micro $api
     *
     * @return bool
     */
    public function call(Micro $api)
    {
        try {
            /** @var Request $request */
            $request = $api->getService('request');

            if (true === $request->isPost() &&
                true !== $request->isLoginPage() &&
                true !== $request->isEmptyBearerToken()) {
                /**
                 * This is where we will validate the token that was sent to us
                 * using Bearer Authentication
                 */
                $token       = $request->getBearerTokenFromHeader();
                $user        = $this->getUserByToken($token, 'Invalid Token');
                $parsedToken = (new Parser())->parse($token);

                $signer = new Sha512();
                $valid  = $token->$parsedToken($signer, $user->get('usr_token_password'));

                /**
                 * Check signed token
                 */
                if (false === $valid) {
                    throw new Exception('Invalid Token');
                }

                $data  = $this->getValidation($user);
                $valid = $parsedToken->validate($data);

                if (false === $valid) {
                    throw new Exception('Invalid Token');
                }
            }

            return true;
        } catch (Exception $ex) {
            $this->halt($api, $ex->getMessage());

            return false;
        }
    }

    /**
     * @param Users $user
     *
     * @return ValidationData
     * @throws ModelException
     */
    private function getValidation(Users $user)
    {
        $validationData = new ValidationData();
        $validationData->setIssuer($user->get('usr_domain_name'));
        $validationData->setAudience('https://phalconphp.com');
        $validationData->setId($user->get('usr_token_id'));
        $validationData->setCurrentTime(time() + 10);

        return $validationData;
    }
}
