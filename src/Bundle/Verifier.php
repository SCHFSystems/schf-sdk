<?php

namespace SCHF\SDK\Bundle;

class Verifier
{
    public function verify(string $bundlePath, ?string $publicKeyPath = null): array
    {
        if (!file_exists($bundlePath)) {
            return ['verified' => false, 'error' => "Bundle not found: {$bundlePath}"];
        }

        $validator = new Validator();
        $validation = $validator->validate($bundlePath);

        if (!$validation['valid']) {
            return [
                'verified' => false,
                'error' => 'Bundle validation failed',
                'validation_errors' => $validation['errors'],
            ];
        }

        $extractDir = $validator->getExtractDir();
        $sigPath = "{$extractDir}/signature.sig";
        $keyPath = $publicKeyPath ?? "{$extractDir}/signing-key.pub";

        if (!file_exists($sigPath)) {
            return ['verified' => false, 'error' => 'Bundle is not signed (no signature.sig found)'];
        }

        if (!file_exists($keyPath)) {
            return ['verified' => false, 'error' => 'No public key available for verification'];
        }

        $signature = file_get_contents($sigPath);
        $checksumData = "{$extractDir}/checksum.sha256";
        if (!file_exists($checksumData)) {
            return ['verified' => false, 'error' => 'Missing checksum.sha256 for signature verification'];
        }
        $data = file_get_contents($checksumData);
        $publicKey = file_get_contents($keyPath);

        $isRsa = str_contains($publicKey, 'BEGIN PUBLIC KEY') || str_contains($publicKey, 'BEGIN RSA PUBLIC KEY');

        $verified = false;
        $algorithm = null;

        if ($isRsa && function_exists('openssl_verify')) {
            $pubKeyId = @openssl_get_publickey($publicKey);
            if ($pubKeyId) {
                $result = @openssl_verify($data, $signature, $pubKeyId, OPENSSL_ALGO_SHA256);
                $verified = $result === 1;
                @openssl_free_key($pubKeyId);
                $algorithm = Signer::ALGORITHM_RSA;
            }
        } elseif (!$isRsa && function_exists('sodium_crypto_sign_verify_detached')) {
            try {
                $key = strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                    ? $publicKey
                    : sodium_crypto_sign_publickey($publicKey);
                $verified = sodium_crypto_sign_verify_detached($signature, $data, $key);
                $algorithm = Signer::ALGORITHM_ED25519;
            } catch (\Throwable $e) {
                return ['verified' => false, 'error' => 'Ed25519 verification failed: ' . $e->getMessage()];
            }
        } else {
            return ['verified' => false, 'error' => 'No suitable crypto extension available'];
        }

        return [
            'verified' => $verified,
            'algorithm' => $algorithm,
            'signed_data' => 'checksum.sha256',
            'public_key_source' => $publicKeyPath ?? 'bundled',
        ];
    }
}
