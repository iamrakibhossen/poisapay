<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * Serves the OpenAPI spec as JSON and a self-contained Swagger UI page (Wave 7).
 * Public + lightly cached; the spec is authored in resources/openapi.yaml.
 */
class OpenApiController extends Controller
{
    public function spec(): JsonResponse
    {
        $spec = Yaml::parseFile(resource_path('openapi.yaml'));

        return response()->json($spec);
    }

    public function ui(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
  <title>PoisaPay API</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.onload = () => SwaggerUIBundle({ url: '/api/openapi.json', dom_id: '#swagger-ui' });
  </script>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }
}
