<?php

namespace App\Services\Verifactu;

use App\Exceptions\Verifactu\VerifactuCertificateException;
use App\Models\Verifactu\VerifactuCertificate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Upload/replace/delete for a taxpayer's AEAT certificate. See
 * docs/verifactu-aeat-connectivity.md §3 for the full security model
 * this implements (and its explicit limitations).
 */
class VerifactuCertificateService
{
    /**
     * @throws VerifactuCertificateException if the file isn't a valid
     *         PKCS#12 openable with the given passphrase.
     */
    public function upload(string $nif, string $pkcs12Bytes, string $passphrase): VerifactuCertificate
    {
        $metadata = $this->parse($pkcs12Bytes, $passphrase);

        $existing = VerifactuCertificate::where('nif', $nif)->first();
        if ($existing) {
            $this->deleteEncryptedFile($existing);
        }

        $path = $this->storeEncrypted($nif, $pkcs12Bytes);

        $certificate = VerifactuCertificate::updateOrCreate(
            ['nif' => $nif],
            array_merge($metadata, [
                'encrypted_file_path' => $path,
                'passphrase'          => $passphrase,
                'uploaded_at'         => now(),
                'last_validated_at'   => now(),
            ])
        );

        Log::channel('verifactu')->info('verifactu.certificate.uploaded', [
            'nif'     => $nif,
            'subject' => $metadata['subject'],
            'valid_to' => $metadata['valid_to']?->toDateString(),
        ]);

        return $certificate;
    }

    public function delete(string $nif): void
    {
        $certificate = VerifactuCertificate::where('nif', $nif)->first();

        if (!$certificate) {
            return;
        }

        $this->deleteEncryptedFile($certificate);
        $certificate->delete();

        Log::channel('verifactu')->info('verifactu.certificate.deleted', ['nif' => $nif]);
    }

    /**
     * @return array{subject: string, issuer: string, serial_number: string, valid_from: \Carbon\Carbon, valid_to: \Carbon\Carbon}
     * @throws VerifactuCertificateException
     */
    private function parse(string $pkcs12Bytes, string $passphrase): array
    {
        $parsed = [];
        $ok     = @openssl_pkcs12_read($pkcs12Bytes, $parsed, $passphrase);

        if (!$ok || empty($parsed['cert'])) {
            throw new VerifactuCertificateException(match (app()->getLocale()) {
                'fr'    => 'Le fichier n\'est pas un certificat PKCS#12 valide, ou le mot de passe indiqué est incorrect.',
                'es'    => 'El archivo no es un certificado PKCS#12 válido, o la contraseña indicada es incorrecta.',
                default => 'The file is not a valid PKCS#12 certificate, or the provided password is incorrect.',
            });
        }

        $info = openssl_x509_parse($parsed['cert']);

        if (!$info) {
            throw new VerifactuCertificateException(match (app()->getLocale()) {
                'fr'    => 'Impossible de lire les informations du certificat.',
                'es'    => 'No se pudo leer la información del certificado.',
                default => 'Unable to read the certificate information.',
            });
        }

        return [
            'subject'       => $this->formatDn($info['subject'] ?? []),
            'issuer'        => $this->formatDn($info['issuer'] ?? []),
            'serial_number' => (string) ($info['serialNumberHex'] ?? $info['serialNumber'] ?? ''),
            'valid_from'    => \Carbon\Carbon::createFromTimestamp($info['validFrom_time_t']),
            'valid_to'      => \Carbon\Carbon::createFromTimestamp($info['validTo_time_t']),
        ];
    }

    private function formatDn(array $dn): string
    {
        $parts = [];
        foreach ($dn as $key => $value) {
            $parts[] = $key . '=' . (is_array($value) ? implode(',', $value) : $value);
        }

        return implode(', ', $parts);
    }

    private function storeEncrypted(string $nif, string $pkcs12Bytes): string
    {
        $path = 'verifactu-certificates/' . $nif . '.p12.enc';
        Storage::disk('local')->put($path, Crypt::encryptString($pkcs12Bytes));

        return $path;
    }

    private function deleteEncryptedFile(VerifactuCertificate $certificate): void
    {
        if ($certificate->encrypted_file_path && Storage::disk('local')->exists($certificate->encrypted_file_path)) {
            Storage::disk('local')->delete($certificate->encrypted_file_path);
        }
    }
}
