<?php

namespace SCHF\SDK\Bundle;

class Signer
{
    public const ALGORITHM_RSA = 'rsa';
    public const ALGORITHM_ED25519 = 'ed25519';

    public function sign(string $bundlePath, string $privateKeyPath, ?string $publicKeyOutput = null): array
    {
        if (!file_exists($bundlePath)) {
            return ['success' => false, 'error' => "Bundle not found: {$bundlePath}"];
        }

        if (!file_exists($privateKeyPath)) {
            return ['success' => false, 'error' => "Private key not found: {$privateKeyPath}"];
        }

        $workDir = sys_get_temp_dir() . '/schf_sign_' . bin2hex(random_bytes(8));
        $validator = new Validator();
        $validation = $validator->validate($bundlePath);

        if (!$validation['valid']) {
            $this->cleanup($workDir);
            return ['success' => false, 'error' => 'Invalid bundle', 'validation_errors' => $validation['errors']];
        }

        $extractDir = $validator->getExtractDir();
        $privateKey = file_get_contents($privateKeyPath);
        $checksumPath = "{$extractDir}/checksum.sha256";
        $checksumData = file_exists($checksumPath) ? file_get_contents($checksumPath) : '';

        $isRsa = !str_contains($privateKey, 'BEGIN PRIVATE KEY') && (str_contains($privateKey, 'BEGIN RSA PRIVATE KEY') || !str_contains($privateKey, 'PRIVATE KEY'));

        $signature = null;
        $algorithm = null;

        if ($isRsa && function_exists('openssl_sign')) {
            $pkeyId = @openssl_get_privatekey($privateKey);
            if (!$pkeyId) {
                $this->cleanup($workDir);
                return ['success' => false, 'error' => 'Invalid RSA private key'];
            }
            @openssl_sign($checksumData, $signature, $pkeyId, OPENSSL_ALGO_SHA256);
            @openssl_free_key($pkeyId);
            $algorithm = self::ALGORITHM_RSA;

            if ($publicKeyOutput) {
                $pubKey = @openssl_get_publickey($privateKey);
                if (!$pubKey) {
                    $pkeyDetails = @openssl_pkey_get_details($pkeyId ?: @openssl_get_privatekey($privateKey));
                    if ($pkeyDetails) {
                        file_put_contents($publicKeyOutput, $pkeyDetails['key']);
                    }
                } else {
                    $pubDetails = @openssl_pkey_get_details($pubKey);
                    if ($pubDetails) {
                        file_put_contents($publicKeyOutput, $pubDetails['key']);
                    }
                    @openssl_free_key($pubKey);
                }
            }
        } elseif (function_exists('sodium_crypto_sign_detached')) {
            $keyPair = @sodium_crypto_sign_secretkey($privateKey);
            if ($keyPair === false) {
                $seed = $privateKey;
                $keyPair = sodium_crypto_sign_seed_keypair($seed);
            } else {
                $keyPair = sodium_crypto_sign_keypair_from_secretkey_and_publickey(
                    $privateKey,
                    sodium_crypto_sign_publickey_from_secretkey($privateKey)
                );
            }
            $signature = sodium_crypto_sign_detached($checksumData, $keyPair);
            $algorithm = self::ALGORITHM_ED25519;

            if ($publicKeyOutput) {
                $pubKey = sodium_crypto_sign_publickey_from_secretkey($privateKey);
                file_put_contents($publicKeyOutput, $pubKey);
            }
        } else {
            $this->cleanup($workDir);
            return ['success' => false, 'error' => 'No signing extension available (openssl or sodium required)'];
        }

        $sigDir = dirname($bundlePath);
        file_put_contents("{$sigDir}/signature.sig", $signature);

        if ($publicKeyOutput && !file_exists($publicKeyOutput)) {
            $pubKeyData = $this->derivePublicKey($privateKey);
            if ($pubKeyData) {
                file_put_contents($publicKeyOutput, $pubKeyData);
            }
        }

        $this->rebuildBundle($bundlePath, $extractDir, $signature, $publicKeyOutput);

        $this->cleanup($workDir);

        return [
            'success' => true,
            'algorithm' => $algorithm,
            'signature_path' => dirname($bundlePath) . '/signature.sig',
            'bundle_path' => $bundlePath,
        ];
    }

    private function rebuildBundle(string $bundlePath, string $extractDir, string $signature, ?string $publicKeyPath): void
    {
        file_put_contents("{$extractDir}/signature.sig", $signature);

        if ($publicKeyPath && file_exists($publicKeyPath)) {
            copy($publicKeyPath, "{$extractDir}/signing-key.pub");
        }

        $zip = new \ZipArchive();
        if ($zip->open($bundlePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to rebuild signed bundle: {$bundlePath}");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($extractDir) + 1));
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();
    }

    private function derivePublicKey(string $privateKey): ?string
    {
        if (function_exists('openssl_get_privatekey')) {
            $pkeyId = @openssl_get_privatekey($privateKey);
            if ($pkeyId) {
                $details = @openssl_pkey_get_details($pkeyId);
                @openssl_free_key($pkeyId);
                return $details['key'] ?? null;
            }
        }

        if (function_exists('sodium_crypto_sign_publickey_from_secretkey')) {
            try {
                return sodium_crypto_sign_publickey_from_secretkey($privateKey);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function cleanup(string $dir): void
    {
        if (is_dir($dir)) {
            $this->removeDirectory($dir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
