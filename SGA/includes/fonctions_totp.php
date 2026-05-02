<?php

declare(strict_types=1);

/**
 * TOTP (RFC 6238 / RFC 4226 HOTP). Secret en Base32 comme pour Google Authenticator.
 */
function sga_base32_decode(string $encoded): string
{
    $encoded = strtoupper($encoded);
    $encoded = str_replace([' ', '-'], '', $encoded);
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $lut = [];

    for ($i = 0; $i < strlen($chars); ++$i) {
        $lut[$chars[$i]] = $i;
    }

    $buffer = '';
    $bitsCollected = 0;
    $value = 0;

    foreach (str_split($encoded) as $ch) {
        if (!isset($lut[$ch])) {
            continue;
        }

        $value = ($value << 5) | $lut[$ch];
        $bitsCollected += 5;

        if ($bitsCollected >= 8) {
            $bitsCollected -= 8;
            $buffer .= chr(($value >> $bitsCollected) & 0xff);
        }
    }

    return $buffer;
}

function sga_hotp_truncated_raw(string $keyBinary, int $counter): string
{
    if ($counter < 0) {
        throw new InvalidArgumentException('Compteur HOTP invalide.');
    }

    $binCounter = pack('N*', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
    $hash = hash_hmac('sha1', $binCounter, $keyBinary, true);
    $tail = substr($hash, -1);

    if ($tail === false || strlen($tail) !== 1) {
        throw new RuntimeException('HMAC OTP : hash invalide.');
    }

    $offset = ord($tail) & 0x0f;
    $block = substr($hash, $offset, 4);

    if ($block === false || strlen($block) !== 4) {
        throw new RuntimeException('Bloc OTP incomplet.');
    }

    [$codeFull] = array_values(unpack('N', $block));
    $code = $codeFull & 0x7fffffff;

    return str_pad((string) ($code % 1000000), 6, '0', STR_PAD_LEFT);
}

function sga_totp_code(string $secretBase32, ?int $timeSlice = null): string
{
    $timeSlice ??= intdiv(time(), 30);
    $key = sga_base32_decode($secretBase32);

    return sga_hotp_truncated_raw($key, $timeSlice);
}

function sga_totp_verifier(string $secretBase32, string $codeSaisi, int $fenetreSecondesDesync = 1): bool
{
    $codeSaisi = preg_replace('/\s+/', '', $codeSaisi) ?? '';
    $codeSaisi = trim($codeSaisi);

    if (!preg_match('/^\d{6}$/', $codeSaisi)) {
        return false;
    }

    $sliceCourant = intdiv(time(), 30);

    try {
        for ($i = -$fenetreSecondesDesync; $i <= $fenetreSecondesDesync; ++$i) {
            $attendu = sga_totp_code($secretBase32, $sliceCourant + $i);

            if (hash_equals($attendu, $codeSaisi)) {
                return true;
            }
        }
    } catch (Throwable) {
        return false;
    }

    return false;
}
