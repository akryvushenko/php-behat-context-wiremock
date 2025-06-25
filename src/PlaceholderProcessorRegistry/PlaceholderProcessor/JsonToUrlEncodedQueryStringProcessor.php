<?php

namespace Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;

class JsonToUrlEncodedQueryStringProcessor implements PlaceholderProcessorInterface
{
    private const NAME = 'json_to_url_encoded_query_string';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @throws WiremockContextException
     */
    public function process(array $args): string|int
    {
        $stubsDirectory = $args[0];
        $filename = $args[1];
        $ignoredCharacters = $args[2];

        if (!is_array($ignoredCharacters)) {
            throw new WiremockContextException('Ignored characters must be an array');
        }

        $absolutePath = $stubsDirectory . '/' . $filename;

        if (!file_exists($absolutePath)) {
            throw new WiremockContextException(
                sprintf('File not found: %s', $absolutePath)
            );
        }

        $fileContent = file_get_contents($absolutePath);
        if ($fileContent === false) {
            throw new WiremockContextException(
                sprintf('Unable to read file: %s', $absolutePath)
            );
        }

        $jsonData = json_decode(trim($fileContent), true);
        if ($jsonData === null) {
            throw new WiremockContextException(
                sprintf('Invalid JSON in file: %s', $absolutePath)
            );
        }

        foreach ($jsonData as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $jsonData[$key] = json_encode($value);
            }
        }

        $queryString = http_build_query($jsonData, '', '&', PHP_QUERY_RFC3986);

        foreach ($ignoredCharacters as $ignoredCharacter) {
            $encoded = rawurlencode($ignoredCharacter);
            $queryString = str_replace($encoded, $ignoredCharacter, $queryString);
        }

        return $queryString;
    }
}
