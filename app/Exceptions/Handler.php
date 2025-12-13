<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        // Si le domaine ne correspond à aucun tenant
        if ($e instanceof TenantCouldNotBeIdentifiedOnDomainException) {

            // 🔹 Option 1 : simple 404
            // return abort(404);

            // 🔹 Option 2 : page custom 404 tenant
            // return response()->view('errors.tenant-not-found', [], 404);

            // 🔹 Option 3 : rediriger vers le site principal
            return redirect()->away('https://fakturalista.com');
        }

        return parent::render($request, $e);
    }
}
