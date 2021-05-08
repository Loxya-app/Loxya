<?php
declare(strict_types=1);

namespace Robert2\API\Controllers;

use DI\Container;
use Robert2\API\Errors\ValidationException;
use Robert2\API\Models\User;
use Robert2\API\Services\Auth;
use Robert2\API\Validation\Validator as V;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;
use \phpCAS;

class AuthController extends BaseController
{
    /** @var Auth */
    protected $auth;

    /** @var array */
    private $settings;

    public function __construct(Container $container, Auth $auth)
    {
        parent::__construct($container);

        $this->auth = $auth;
        $this->settings = $container->get('settings');
    }

    public function getSelf(Request $request, Response $response): Response
    {
        return $response->withJson(Auth::user(), SUCCESS_OK);
    }

    public function loginWithForm(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $this->_validateAuthRequest($data);

        $user = User::fromLogin($data['identifier'], $data['password']);

        $responseData['user'] = $user->append('restricted_parks')->toArray();
        $responseData['token'] = Auth\JWT::generateToken($user);

        return $response->withJson($responseData, SUCCESS_OK);
    }

    public function loginWithCAS(Request $request, Response $response): Response
    {
        if (!$this->settings['auth']['CAS']['enabled']) {
            return $response->withRedirect('/');
        }

        try {
            Auth\CAS::initializeCAS();

            $isAuthenticated = phpCAS::forceAuthentication();
            if (!$isAuthenticated) {
                throw new \Exception("L'authentification CAS a échoué (absence de redirection vers le serveur CAS).");
            }
        } catch (\Throwable $e) {
            debug($e->getMessage(), ['log' => true, 'append' => false]);
            // TODO: Ajouter un message d'erreur passé au client (lorsqu'on aura un moyen de le faire)
            //       l'information du fait que la connexion a échoué.
        }

        // TODO: globalConfig['client_url'] à la place de '/' ?
        return $response->withRedirect('/');
    }

    public function logout(Request $request, Response $response)
    {
        if (!$this->auth->logout()) {
            // TODO: Ajouter un message d'erreur passé au client (lorsqu'on aura un moyen de le faire)
            //       l'informant du fait qu'il n'a pas été complétement
            //       déconnécté.
            return $response->withRedirect('/');
        }
        return $response->withRedirect('/login#bye');
    }

    // ——————————————————————————————————————————————————————
    // —
    // —    Internal Methods
    // —
    // ——————————————————————————————————————————————————————

    protected function _validateAuthRequest(array $data): void
    {
        $valid  = true;
        $errors = ['identifier' => [], 'password' => []];

        if (!isset($data['identifier']) || !V::notEmpty()->validate($data['identifier'])) {
            $errors['identifier'][] = "Identifier must not be empty";
            $valid = false;
        }

        if (!isset($data['password']) || !V::notEmpty()->validate($data['password'])) {
            $errors['password'][] = "Password must not be empty";
            $valid = false;
        }

        if (isset($data['password']) && !V::length(4)->validate($data['password'])) {
            $errors['password'][] = "Password must have a length greater than 4";
            $valid = false;
        }

        if (!$valid) {
            throw (new ValidationException)
                ->setValidationErrors($errors);
        }
    }
}
