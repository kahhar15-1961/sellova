<?php
require __DIR__.'/vendor/autoload.php';
$bootstrap = require __DIR__.'/bootstrap/app.php';
$console = $bootstrap->make(Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();

$app = new App\Http\AppServices();
$routeFactory = require __DIR__.'/routes/api.php';
$routes = $routeFactory($app);
$http = new App\Http\HttpKernel($app, $routes);

$user = App\Models\User::query()->create([
    'uuid' => (string) Illuminate\Support\Str::uuid(),
    'email' => 'tmp'.random_int(1,99999).'@example.test',
    'password_hash' => password_hash('secret1234', PASSWORD_DEFAULT),
    'status' => 'active',
    'risk_level' => 'low',
]);

$plain = 'at_'.Illuminate\Support\Str::random(48);
App\Models\UserAuthToken::query()->create([
    'uuid' => (string) Illuminate\Support\Str::uuid(),
    'user_id' => $user->id,
    'token_family' => (string) Illuminate\Support\Str::uuid(),
    'token_hash' => hash('sha256', $plain),
    'kind' => App\Models\UserAuthToken::KIND_ACCESS,
    'expires_at' => now()->addHour(),
    'revoked_at' => null,
    'created_at' => now(),
]);

$server = [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$plain,
];

$req = Symfony\Component\HttpFoundation\Request::create('/api/v1/me/payment-methods', 'POST', [], [], [], $server, json_encode([
    'kind' => 'card',
    'label' => 'Visa **** 1111',
    'subtitle' => 'Expires 08/30',
    'is_default' => true,
]));
$res = $http->handle($req);
$j = json_decode($res->getContent(), true);
echo "create status={$res->getStatusCode()}
";
var_export($j);
echo "
";
$id = (int)($j['data']['id'] ?? 0);

$req2 = Symfony\Component\HttpFoundation\Request::create('/api/v1/me/payment-methods/'.$id, 'PATCH', [], [], [], $server, json_encode([]));
$res2 = $http->handle($req2);
echo "patch status={$res2->getStatusCode()}
";
echo $res2->getContent()."
";
