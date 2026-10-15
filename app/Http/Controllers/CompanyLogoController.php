<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Entrega o logotipo da empresa (armazenado em disco privado) somente para
 * usuários com acesso àquela empresa.
 */
class CompanyLogoController extends Controller
{
    public function __invoke(Request $request, Company $company): Response
    {
        abort_unless($request->user()->canAccessCompany($company), 403);

        $contents = $company->logoContents();
        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'image/png',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
