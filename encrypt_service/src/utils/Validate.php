<?php
namespace ChiwiCrypt\utils;
class Validate
{
  /**
   * The function `validateArray` checks if all required keys are present in an array and returns any
   * missing fields or `true`.
   * 
   * Args:
   *   array (array): The `array` parameter is the array that you want to validate. It should contain
   * the data that you want to check for the presence of specific keys.
   *   keys (array): The `` parameter in the `validateArray` function is an array containing the
   * keys that should be present in the input array for validation.
   * 
   * Returns:
   *   an array of missing fields if any are found, otherwise it returns `true`.
   */
  public static function validateArray(array $array, array $keys): mixed
  {
    $missingFields = array_diff($keys, array_keys(array_filter($array)));
    return $missingFields ?? true;
  }
  /**
   * The function `parseTypeErrorMessage` in PHP parses a raw error message related to type mismatches
   * in function arguments and returns a formatted error message.
   * 
   * Args:
   *   rawMessage (string): Argument #1 () must be of type string, null given...
   * 
   * Returns:
   *   The `parseTypeErrorMessage` function returns a formatted error message indicating that a
   * parameter must be of a specific type, along with the expected type and the actual type that was
   * given. If the raw message matches the expected format, it will return a message like: "El
   * parámetro 'userId' debe ser de tipo string (se recibió null)". If the raw message does not match
   * the expected
   */
  public static function parseTypeErrorMessage(string $rawMessage): string
  {
    // Ejemplo de mensaje crudo: 
    // "Argument #1 ($userId) must be of type string, null given..."

    if (preg_match('/Argument #\d+ \(\$(\w+)\) must be of type (\w+), (.+?) given/', $rawMessage, $matches)) {
      $argName = $matches[1];  // Ej: "userId"
      $expectedType = $matches[2]; // Ej: "string"
      $givenType = $matches[3];   // Ej: "null"

      return sprintf("El parámetro '%s' debe ser de tipo %s (se recibió %s)", $argName, $expectedType, $givenType);
    }
    return "Tipo de parámetro inválido";
  }
}
