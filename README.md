# A simple PHP Rest API library

This library helps you to create a rest API by adding convenient tools and structure to the PHP code-base.

## Getting started

An example of how you can configure the rest API. In the root folder of where you want your API to resinde at the PHP server create a .htaccess file that rewrites all subtrafic to this file.
```
RewriteEngine On
RewriteRule ^(.*) index.php?_=$1 [QSA,L]
```
Next create the index.php file with the API endpoints you would want to use.
```
use \gd\rest\App;
use \gd\rest\Request;
use \gd\rest\Response;
$app = new App();
$app->setRequestPathName('/' . $_GET['_']);
unset($_GET['_']);

// Declare endpoints:
$app->get('/api/v1/ping', function (Request $request, Response $response) {
		$response->end('pong');
	})
  ->get('/api/v1/short-url', function (Request $request, Response $response) {
		$response->redirectTo($request->getUrl() . '/api');
	})
	->get('/api/v1/api', function (Request $request, Response $response) {
		include('docs/index.php'); // will serve the documentation with it's own logic
	})
	->get('/app/content', function (Request $request, Response $response) {
		$response->end(file_get_contents('content/index.html'));
	})
  ->use('/app', '../static/pages') // Serve normal files as a file server
  ->post('api/v1/update, authenticate, function (Request $request, Response $response) {
    $params = $request->getPostParams([
      'id' => [ 'match' => '/\d+/', 'required' => true, 'default' => null ]
    ]);
    if ($params === false) $response->withStatus(400)->end('Invalid params, id must be numeric!');
    // TODO: update id =)
    $response->withStatus(200)->end('Successfully updated id');
  });
```

Additional features supported are file uploading, put and delete requests as well as request chaining and forwarding. 
