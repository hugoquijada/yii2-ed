<?php

namespace eDesarrollos\totp;

/**
 * PHP Class for handling Google Authenticator 2-factor authentication.
 *
 * @author Michael Kliewe
 * @copyright 2012 Michael Kliewe
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 *
 * @link http://www.phpgangsta.de/
 */
class Authenticator {

  protected $_longitudCodigo = 6;

  /**
   * Crear semilla.
   * 16 caracteres, elegidos aleatoriamente de una cadena base 32.
   *
   * @param int $longitudSemilla
   *
   * @return string
   */
  public function crearSemilla($longitudSemilla = 16) {
    $caracteresValidos = $this->_getBase32LookupTable();

    // Valid secret lengths are 80 to 640 bits
    if ($longitudSemilla < 16 || $longitudSemilla > 128) {
      throw new \Exception('Bad secret length');
    }
    $secret = '';
    $rnd = false;
    if (function_exists('random_bytes')) {
      $rnd = random_bytes($longitudSemilla);
    } elseif (function_exists('mcrypt_create_iv')) {
      $rnd = mcrypt_create_iv($longitudSemilla, MCRYPT_DEV_URANDOM);
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
      $rnd = openssl_random_pseudo_bytes($longitudSemilla, $cryptoStrong);
      if (!$cryptoStrong) {
        $rnd = false;
      }
    }
    if ($rnd !== false) {
      for ($i = 0; $i < $longitudSemilla; ++$i) {
        $secret .= $caracteresValidos[ord($rnd[$i]) & 31];
      }
    } else {
      throw new \Exception('No source of secure random');
    }

    return $secret;
  }

  /**
   * Calcula el código, usando la semilla y una fracción de tiempo.
   *
   * @param string   $semilla
   * @param int|null $fraccion
   *
   * @return string
   */
  public function obtenerCodigo($semilla, $fraccion = null) {
    if ($fraccion === null) {
      $fraccion = floor(time() / 30);
    }

    $llave = $this->_base32Decode($semilla);

    // Pack time into binary string
    $tiempo = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $fraccion);
    // Hash it with users secret key
    $hm = hash_hmac('SHA1', $tiempo, $llave, true);
    // Use last nipple of result as index/offset
    $offset = ord(substr($hm, -1)) & 0x0F;
    // grab 4 bytes of the result
    $hashpart = substr($hm, $offset, 4);

    // Unpak binary value
    $value = unpack('N', $hashpart);
    $value = $value[1];
    // Only 32 bits
    $value = $value & 0x7FFFFFFF;

    $modulo = pow(10, $this->_longitudCodigo);

    return str_pad($value % $modulo, $this->_longitudCodigo, '0', STR_PAD_LEFT);
  }

  /**
   * Obtener una url de una imagen QR
   *
   * @param string $nombre
   * @param string $semilla
   * @param string $titulo
   * @param array  $params
   *
   * @return string
   */
  public function getQRUrl($nombre, $semilla, $titulo = null, $params = []) {
    $width  = isset($params['width']) && (int) $params['width'] > 0 ? (int) $params['width'] : 200;
    $height = isset($params['height']) && (int) $params['height'] > 0 ? (int) $params['height'] : 200;
    $level  = isset($params['level']) && array_search($params['level'],['L', 'M', 'Q', 'H']) !== false ? $params['level'] : 'M';

    $urlencoded = urlencode('otpauth://totp/' . $nombre . '?secret=' . $semilla . '');
    if (isset($titulo)) {
      $urlencoded .= urlencode('&issuer=' . urlencode($titulo));
    }

    return "https://api.qrserver.com/v1/create-qr-code/?data={$urlencoded}&size={$width}x{$height}&ecc={$level}";
  }

  /**
   * Comprobar si el código es correcto. Aceptará códigos desde $discrepancia * 30 segundos, antes y después de ahora
   *
   * @param string   $semilla
   * @param string   $codigo
   * @param int      $discrepancia permite un rango de tiempo (8 significa 4 minutos antes o después)
   * @param int|null $fraccion fracción de tiempo si no queremos usar time()
   *
   * @return bool
   */
  public function verifyCode($semilla, $codigo, $discrepancia = 1, $fraccion = null) {
    if ($fraccion === null) {
      $fraccion = floor(time() / 30);
    }

    if (strlen($codigo) != 6) {
      return false;
    }

    for ($i = -$discrepancia; $i <= $discrepancia; ++$i) {
      $codigoCalculado = $this->obtenerCodigo($semilla, $fraccion + $i);
      if ($this->timingSafeEquals($codigoCalculado, $codigo)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Asigna la longitud del código, debe ser mayor que 6 caracteres
   *
   * @param int $longitud
   *
   * @return Authenticator
   */
  public function longitudCodigo($longitud) {
    $this->_longitudCodigo = $longitud;

    return $this;
  }

  /**
   * Helper class to decode base32.
   *
   * @param $secret
   *
   * @return bool|string
   */
  protected function _base32Decode($secret) {
    if (empty($secret)) {
      return '';
    }

    $base32chars = $this->_getBase32LookupTable();
    $base32charsFlipped = array_flip($base32chars);

    $paddingCharCount = substr_count($secret, $base32chars[32]);
    $allowedValues = array(6, 4, 3, 1, 0);
    if (!in_array($paddingCharCount, $allowedValues)) {
      return false;
    }
    for ($i = 0; $i < 4; ++$i) {
      if (
        $paddingCharCount == $allowedValues[$i] &&
        substr($secret, - ($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])
      ) {
        return false;
      }
    }
    $secret = str_replace('=', '', $secret);
    $secret = str_split($secret);
    $binaryString = '';
    for ($i = 0; $i < count($secret); $i = $i + 8) {
      $x = '';
      if (!in_array($secret[$i], $base32chars)) {
        return false;
      }
      for ($j = 0; $j < 8; ++$j) {
        $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
      }
      $eightBits = str_split($x, 8);
      for ($z = 0; $z < count($eightBits); ++$z) {
        $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
      }
    }

    return $binaryString;
  }

  /**
   * Get array with all 32 characters for decoding from/encoding to base32.
   *
   * @return array
   */
  protected function _getBase32LookupTable() {
    return array(
      'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
      'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
      'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
      'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
      '=',  // padding char
    );
  }

  /**
   * A timing safe equals comparison
   * more info here: http://blog.ircmaxell.com/2014/11/its-all-about-time.html.
   *
   * @param string $safeString The internal (safe) value to be checked
   * @param string $userString The user submitted (unsafe) value
   *
   * @return bool True if the two strings are identical
   */
  private function timingSafeEquals($safeString, $userString) {
    if (function_exists('hash_equals')) {
      return hash_equals($safeString, $userString);
    }
    $safeLen = strlen($safeString);
    $userLen = strlen($userString);

    if ($userLen != $safeLen) {
      return false;
    }

    $result = 0;

    for ($i = 0; $i < $userLen; ++$i) {
      $result |= (ord($safeString[$i]) ^ ord($userString[$i]));
    }

    // They are only identical strings if $result is exactly 0...
    return $result === 0;
  }
}
