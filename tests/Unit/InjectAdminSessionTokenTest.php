<?php

namespace Tests\Unit;

use App\Http\Middleware\Admin\InjectAdminSessionToken;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Session\ArraySessionHandler;
use Tests\TestCase;

class InjectAdminSessionTokenTest extends TestCase
{
    public function test_copia_el_token_de_sesion_al_header_authorization(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $session->put('admin_token', 'abc123');

        $request = Request::create('/admin/catalogos/paises', 'GET');
        $request->setLaravelSession($session);

        $middleware = new InjectAdminSessionToken();
        $capturedHeader = null;

        $middleware->handle($request, function ($req) use (&$capturedHeader) {
            $capturedHeader = $req->headers->get('Authorization');
            return response('ok');
        });

        $this->assertSame('Bearer abc123', $capturedHeader);
    }
}
