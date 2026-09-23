<?php

namespace App\Http\Controllers;

use App\Exceptions\Verifactu\VerifactuCertificateException;
use App\Models\CompanyProfile;
use App\Models\Verifactu\VerifactuCertificate;
use App\Services\Verifactu\Aeat\AeatEndpointResolver;
use App\Services\Verifactu\VerifactuCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings → VERI*FACTU → Certificado. Only ever returns safe metadata -
 * never the private key, raw certificate bytes, or passphrase. See
 * docs/verifactu-aeat-connectivity.md §3/§4.
 */
class VerifactuCertificateController extends Controller
{
    public function __construct(
        private VerifactuCertificateService $certificates,
        private AeatEndpointResolver $endpoints,
    ) {
    }

    public function show(): JsonResponse
    {
        $company     = CompanyProfile::first();
        $certificate = $company?->tax_id ? VerifactuCertificate::where('nif', trim($company->tax_id))->first() : null;

        return response()->json([
            'environment' => $this->endpoints->environment(),
            'configured'  => (bool) $certificate,
            'certificate' => $certificate ? $this->formatCertificate($certificate) : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'certificate' => 'required|file|max:5120',
            'passphrase'  => 'required|string',
        ]);

        $company = CompanyProfile::first();
        if (empty($company?->tax_id)) {
            return response()->json([
                'message' => match (app()->getLocale()) {
                    'fr'    => 'Configurez d\'abord le NIF/CIF de votre entreprise dans les Paramètres avant de charger un certificat.',
                    'es'    => 'Configura primero el NIF/CIF de tu empresa en Ajustes antes de subir un certificado.',
                    default => 'First configure your company\'s Tax ID (NIF/CIF) in Settings before uploading a certificate.',
                },
            ], 422);
        }

        try {
            $certificate = $this->certificates->upload(
                trim($company->tax_id),
                file_get_contents($validated['certificate']->getRealPath()),
                $validated['passphrase']
            );
        } catch (VerifactuCertificateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'     => match (app()->getLocale()) {
                'fr'    => 'Certificat enregistré avec succès.',
                'es'    => 'Certificado guardado correctamente.',
                default => 'Certificate saved successfully.',
            },
            'certificate' => $this->formatCertificate($certificate),
        ]);
    }

    public function destroy(): JsonResponse
    {
        $company = CompanyProfile::first();
        if (!empty($company?->tax_id)) {
            $this->certificates->delete(trim($company->tax_id));
        }

        return response()->json(['message' => match (app()->getLocale()) {
            'fr'    => 'Certificat supprimé.',
            'es'    => 'Certificado eliminado.',
            default => 'Certificate deleted.',
        }]);
    }

    private function formatCertificate(VerifactuCertificate $certificate): array
    {
        return [
            'subject'            => $certificate->subject,
            'issuer'             => $certificate->issuer,
            'valid_from'         => $certificate->valid_from?->toDateString(),
            'valid_to'           => $certificate->valid_to?->toDateString(),
            'uploaded_at'        => $certificate->uploaded_at?->toDateTimeString(),
            'last_validated_at'  => $certificate->last_validated_at?->toDateTimeString(),
            'status'             => $certificate->status(),
        ];
    }
}
