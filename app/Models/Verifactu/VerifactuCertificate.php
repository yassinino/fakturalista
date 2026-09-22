<?php

namespace App\Models\Verifactu;

use Illuminate\Database\Eloquent\Model;

/**
 * Metadata + encrypted-storage pointer for a taxpayer's AEAT client
 * certificate. Never holds decrypted key material - see
 * VerifactuCertificateService (upload/replace/delete) and
 * Auth\CustomerCertificateProvider (transient decrypt-for-one-request).
 * Security model documented in full in
 * docs/verifactu-aeat-connectivity.md §3.
 */
class VerifactuCertificate extends Model
{
    protected $table = 'verifactu_certificates';

    protected $fillable = [
        'nif',
        'encrypted_file_path',
        'passphrase',
        'subject',
        'issuer',
        'serial_number',
        'valid_from',
        'valid_to',
        'uploaded_at',
        'last_validated_at',
    ];

    protected $casts = [
        'passphrase'         => 'encrypted',
        'valid_from'         => 'datetime',
        'valid_to'           => 'datetime',
        'uploaded_at'        => 'datetime',
        'last_validated_at'  => 'datetime',
    ];

    /**
     * Never expose the passphrase through array/JSON serialization
     * (e.g. an accidental ->toArray() in a log or API response).
     */
    protected $hidden = [
        'passphrase',
        'encrypted_file_path',
    ];

    public function isExpired(): bool
    {
        return $this->valid_to !== null && $this->valid_to->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->valid_to !== null
            && !$this->isExpired()
            && $this->valid_to->isBefore(now()->addDays($days));
    }

    public function status(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }
        if ($this->isExpiringSoon()) {
            return 'expiring_soon';
        }

        return 'ok';
    }
}
