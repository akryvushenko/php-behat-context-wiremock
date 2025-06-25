<?php

namespace Auto1\BehatContext\Wiremock\PlaceholderProcessorRegistry\PlaceholderProcessor;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;

class FlattenTextProcessor implements PlaceholderProcessorInterface
{
    private const NAME = 'flatten_text';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @throws WiremockContextException
     */
    public function process($args): string|int
    {
        $stubsDirectory = $args[0];
        $filename = $args[1];
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

        return preg_replace('/\s+/', ' ', trim($fileContent));
    }
}
