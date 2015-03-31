SimpleAPI for Laravel 4 and 5
==============

A simple api wrapper with quick and easy caching and throttling.

### Configuration

To configure a new api key you must register it in the .env where in the example `EXAMPLE_KEY` is the key.

```php
# Required 
EXAMPLE_KEY_API_URL=http://api.example.com/

# Optional
EXAMPLE_KEY_API_THROTTLE_LIMIT=100
EXAMPLE_KEY_API_STORAGE_TIME=1440
EXAMPLE_KEY_API_CACHE_TIME=60
```

### Usage

Example to resolve http://api.example.com/baz?foo=bar

```php
$api = new SimpleAPI\RegisterAPI('example_key');

$query = [
	'foo' => 'bar'
];

$result = $api->action('baz')->lookup($query);
```

### Error handling

This library works by throwing exceptions which you would need to catch.

- Invalid configuration `ConfigException`
- Throlled API `ThrottleException`
- Request error `RequestException`