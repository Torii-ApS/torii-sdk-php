<?php

declare(strict_types=1);

return [
    /*
    |---------------------------------------------------------------------------
    | Backend secret key
    |---------------------------------------------------------------------------
    |
    | Issued via the torii dashboard. Used to authenticate to
    | `/api/server/v1/*` endpoints. Treat as a server-only secret — never
    | leak it to frontend code.
    |
    */
    'secret_key' => env('TORII_SECRET_KEY'),

    /*
    |---------------------------------------------------------------------------
    | Expected JWT issuer
    |---------------------------------------------------------------------------
    |
    | The FAPI URL for this environment, e.g. `https://acme.torii.so` or a
    | verified custom domain (`https://auth.acme.com`). Required by the
    | `RequireAuth` middleware — strict `iss` validation is the point.
    |
    */
    'issuer' => env('TORII_ISSUER'),

    /*
    |---------------------------------------------------------------------------
    | Backend API base URL
    |---------------------------------------------------------------------------
    |
    | Override only for self-hosted or staging deployments. Defaults to
    | https://api.torii.so when left null.
    |
    */
    'api_url' => env('TORII_API_URL'),
];
