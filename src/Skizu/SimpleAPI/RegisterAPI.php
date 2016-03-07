<?php

namespace SimpleAPI;

use GuzzleHttp\Client;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

Class ThrottleException extends \Exception
{
}

Class ConfigException extends \Exception
{
}

Class RequestException extends \Exception
{
}

Class ResponseException extends \Exception
{
}

Class ServerException extends ResponseException
{
}

class RegisterAPI extends Controller
{
    const THROTTLE_LIMIT = 100;
    const STORAGE_TIME = 1440;
    const THROTTLE_TIME = 60;
    const CACHE_TIME = 60;

    private $method = 'GET';
    private $limit;
    private $storage_time;
    private $cache_key;
    private $cache_time;
    private $auth_user;
    private $auth_key;

    public function __construct($api, $auth = false)
    {
        $this->api = strtoupper($api);

        $api_url_key = $this->api . '_API_URL';
        $this->api_url = env($api_url_key);

        $api_user = $this->api . '_API_USER';
        $this->auth_user = env($api_user);

        $api_key = $this->api . '_API_KEY';
        $this->auth_key = env($api_key);

        $this->limit = env($this->api . '_API_THROTTLE_LIMIT', self::THROTTLE_LIMIT);
        $this->storage_time = env($this->api . '_API_STORAGE_TIME', self::STORAGE_TIME);
        $this->cache_time = env($this->api . '_API_CACHE_TIME', self::CACHE_TIME);
        $this->queries_key = $this->api . '_COUNT';

        if (!isset($this->api_url) || empty($this->api_url)) {
            throw new ConfigException("Unable to find: $api_url_key");
        }

        if ($auth) {
            if (!isset($this->auth_user) || empty($this->api_user)) {
                throw new ConfigException("Unable to find: $api_user");
            } elseif (!isset($this->auth_key) || empty($this->api_key)) {
                throw new ConfigException("Unable to find: $api_key");
            }
        }

    }

    public function action($action)
    {
        return $this->api_url . $action;
    }

    public function lookup($search = null, $method = 'GET')
    {
        $this->cache_key = $this->cacheKey($search);
        $this->method = $method;

        return Cache::get($this->cache_key, function () use ($method, $search) {
            $this->throttleCheck();

            return $this->start($search);
        });
    }

    private function start($search)
    {
        return $this->store($this->queryAPI($search));
    }

    private function queryAPI($search)
    {
        try {
            $client = new Client([
                'defaults' => [
                    'auth' => [
                        $this->auth_user,
                        $this->auth_key,
                    ]
                ]
            ]);

            $request = $client->createRequest($this->method, $this->api_url);
            if ($search) {
                $request->setQuery($search);
            }

            return $client->send($request);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            throw new ServerException($e->getMessage());
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            throw new ResponseException($e->getMessage());
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new RequestException($e->getMessage());
        } catch (\GuzzleHttp\Exception\ClientErrorResponseException $e) {
            throw new ResponseException($e->getMessage());
        }
    }

    private function throttleCheck()
    {
        if ($this->limit <= $this->queries()) {
            throw new ThrottleException('API throttled due to flood of requests');
        }
        Cache::put($this->queries_key, $this->queries() + 1, $this->cache_time);
    }

    private function cacheKey($search)
    {
        return md5($this->api_url . serialize($search));
    }

    private function store($response)
    {
        $result = $this->detectType($response);
        Cache::put($this->cache_key, $result, $this->storage_time);

        return $result;
    }

    private function detectType(\GuzzleHttp\Message\Response $response)
    {
        switch ($response->getHeader('content-type')) {
            case 'application/json':
                return $response->json();
                break;

            case 'application/xml':
            case 'text/xml':
                return json_decode(json_encode($response->xml()), true);
                break;

            default:
                return $response->getBody();
        }

    }

    private function queries()
    {
        return Cache::get($this->queries_key, 0);
    }
}
