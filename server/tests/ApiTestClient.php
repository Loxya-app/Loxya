<?php
declare(strict_types=1);

namespace Robert2\Tests;

use Psr\Http\Message\StreamInterface as Body;
use Robert2\API\App;
use Robert2\API\Http\Request;
use Slim\Http\Response;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * ApiTestClient.
 *
 * @method Body get(string $uri, array $query = null)
 * @method Body post(string $uri, array $data = null)
 * @method Body patch(string $uri, array $data = null)
 * @method Body put(string $uri, array $data = null)
 * @method Body delete(string $uri, array $data = null)
 * @method Body head(string $uri, array $data = null)
 * @method Body options(string $uri, array $data = null)
 */
class ApiTestClient
{
    public App $app;

    public Request $request;

    public Response $response;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function __call($method, $arguments)
    {
        $methods = [ 'get', 'post', 'patch', 'put', 'delete', 'head', 'options'];
        if (!in_array($method, $methods, true)) {
            throw new \BadMethodCallException(sprintf("%s is not supported", strtoupper($method)));
        }
        return call_user_func_array([$this, 'request'], array_merge([$method], $arguments));
    }

    // ------------------------------------------------------
    // -
    // -    Internal methods
    // -
    // ------------------------------------------------------

    protected function request(string $method, string $uri, ?array $data = null): Body
    {
        // - Request
        $method = strtoupper($method);
        $request = new Request((new ServerRequestFactory())->createServerRequest($method, $uri));
        if ($data !== null) {
            if ($method === 'GET') {
                $request = $request->withQueryParams($data);
            } else {
                $request = $request->withParsedBody($data);
                $request = $request->withHeader('Content-Type', 'application/json');
            }
        }
        $this->request = $request;

        // - Response
        $this->response = $this->app->handle($this->request);
        return $this->response->getBody();
    }
}
