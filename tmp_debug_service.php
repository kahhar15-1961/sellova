<?php
require __DIR__.'/vendor/autoload.php';
$bootstrap = require __DIR__.'/bootstrap/app.php';
$console = $bootstrap->make(Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();

$svc = app(App\Services\UserSeller\UserSellerService::class);
$u = App\Models\User::query()->create([
  'uuid'=>(string)Illuminate\Support\Str::uuid(),
  'email'=>'svc'.random_int(1,99999).'@example.test',
  'password_hash'=>password_hash('x', PASSWORD_DEFAULT),
  'status'=>'active',
  'risk_level'=>'low',
]);
$a = $svc->createBuyerPaymentMethod((int)$u->id, ['kind'=>'card','label'=>'Visa','subtitle'=>'sub','is_default'=>true]);
$b = $svc->createBuyerPaymentMethod((int)$u->id, ['kind'=>'bkash','label'=>'bKash','subtitle'=>'sub','is_default'=>false]);
var_export($a); echo "
"; var_export($b); echo "
";
try {
  $c = $svc->setDefaultBuyerPaymentMethod((int)$u->id, (int)$b['id']);
  echo "ok
"; var_export($c); echo "
";
} catch (Throwable $e) {
  echo get_class($e).": ".$e->getMessage()."
";
  echo $e->getTraceAsString()."
";
}
