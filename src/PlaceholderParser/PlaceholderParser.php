<?php

namespace Auto1\BehatContext\Wiremock\PlaceholderParser;

use Auto1\BehatContext\Wiremock\Exception\WiremockContextException;

class PlaceholderParser
{
    public function parse(string $argumentsString): array
    {
        $parsedArguments = $this->parseArguments($argumentsString);

        return array_map([$this, 'convertArgumentsToPHPTyped'], $parsedArguments);
    }

    private function parseArguments(string $argString): array {
        if (trim($argString) === '') {
            return [];
        }

        $args = [];
        $currentArg = '';
        $bracketDepth = 0;
        $insideQuotes = false;
        $quoteChar = '';

        $length = strlen($argString);

        for ($i = 0; $i < $length; $i++) {
            $char = $argString[$i];
            $prevChar = $i > 0 ? $argString[$i - 1] : '';

            // Handle quotes (e.g., 'a', "b")
            if (($char === '"' || $char === "'") && $prevChar !== '\\') {
                if ($insideQuotes && $char === $quoteChar) {
                    // Closing quote
                    $insideQuotes = false;
                    $quoteChar = '';
                } elseif (!$insideQuotes) {
                    // Opening quote
                    $insideQuotes = true;
                    $quoteChar = $char;
                }
            }

            // Track array brackets if outside of quotes
            if (!$insideQuotes) {
                if ($char === '[') {
                    $bracketDepth++;
                } elseif ($char === ']') {
                    $bracketDepth--;
                }
            }

            // Split on top-level commas (not inside quotes or arrays)
            if ($char === ',' && !$insideQuotes && $bracketDepth === 0) {
                $args[] = trim($currentArg);
                $currentArg = '';
            } else {
                $currentArg .= $char;
            }
        }

        // Append the last argument (if any)
        if (trim($currentArg) !== '') {
            $args[] = trim($currentArg);
        }

        return $args;
    }

    /**
     * @throws WiremockContextException
     */
    private function convertArgumentsToPHPTyped(string $arg): mixed
    {
        $arg = trim($arg);

        // Array
        if (str_starts_with($arg, '[') && str_ends_with($arg, ']')) {
            return $this->parseArrayArgument($arg);
        }

        return $this->convertScalarType($arg);
    }

    /**
     * Parse array argument supporting nested arrays and associative arrays
     * @throws WiremockContextException
     */
    private function parseArrayArgument(string $arrayString): array
    {
        $arrayString = trim($arrayString);

        // Remove outer brackets
        $content = substr($arrayString, 1, -1);
        $content = trim($content);

        if ($content === '') {
            return [];
        }

        $elements = $this->parseArrayElements($content);
        $result = [];

        foreach ($elements as $element) {
            $element = trim($element);

            // Check for malformed associative syntax (single '=' instead of '=>')
            if ($this->hasMalformedAssociativeSyntax($element)) {
                throw new WiremockContextException('Invalid associative array syntax: use "=>" instead of "="');
            }

            // Check if this is an associative array element (key => value)
            if ($this->isAssociativeElement($element)) {
                [$key, $value] = $this->parseAssociativeElement($element);
                $result[$key] = $value;
            } else {
                // Regular indexed array element
                $result[] = $this->convertArgumentsToPHPTyped($element);
            }
        }

        return $result;
    }

    /**
     * Check if an element has malformed associative syntax (single '=' instead of '=>')
     */
    private function hasMalformedAssociativeSyntax(string $element): bool
    {
        $bracketDepth = 0;
        $insideQuotes = false;
        $quoteChar = '';

        $length = strlen($element);

        for ($i = 0; $i < $length; $i++) {
            $char = $element[$i];
            $nextChar = $i < $length - 1 ? $element[$i + 1] : '';
            $prevChar = $i > 0 ? $element[$i - 1] : '';

            // Handle quotes
            if (($char === '"' || $char === "'") && $prevChar !== '\\') {
                if ($insideQuotes && $char === $quoteChar) {
                    $insideQuotes = false;
                    $quoteChar = '';
                } elseif (!$insideQuotes) {
                    $insideQuotes = true;
                    $quoteChar = $char;
                }
            }

            // Track bracket depth if outside quotes
            if (!$insideQuotes) {
                if ($char === '[') {
                    $bracketDepth++;
                } elseif ($char === ']') {
                    $bracketDepth--;
                }
            }

            // Check for single '=' that's not part of '=>' at top level
            if (!$insideQuotes && $bracketDepth === 0 && $char === '=' && $nextChar !== '>') {
                // Make sure it's not part of a comparison or other valid syntax
                // Look for pattern like: word/quoted_string = word/quoted_string
                $beforeEquals = trim(substr($element, 0, $i));
                $afterEquals = trim(substr($element, $i + 1));

                if ($beforeEquals !== '' && $afterEquals !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Parse array elements, respecting nested structures and quotes
     */
    private function parseArrayElements(string $content): array
    {
        $elements = [];
        $currentElement = '';
        $bracketDepth = 0;
        $insideQuotes = false;
        $quoteChar = '';

        $length = strlen($content);

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];
            $prevChar = $i > 0 ? $content[$i - 1] : '';

            // Handle quotes
            if (($char === '"' || $char === "'") && $prevChar !== '\\') {
                if ($insideQuotes && $char === $quoteChar) {
                    $insideQuotes = false;
                    $quoteChar = '';
                } elseif (!$insideQuotes) {
                    $insideQuotes = true;
                    $quoteChar = $char;
                }
            }

            // Track bracket depth if outside quotes
            if (!$insideQuotes) {
                if ($char === '[') {
                    $bracketDepth++;
                } elseif ($char === ']') {
                    $bracketDepth--;
                }
            }

            // Split on top-level commas
            if ($char === ',' && !$insideQuotes && $bracketDepth === 0) {
                $elements[] = trim($currentElement);
                $currentElement = '';
            } else {
                $currentElement .= $char;
            }
        }

        // Add the last element
        if (trim($currentElement) !== '') {
            $elements[] = trim($currentElement);
        }

        return $elements;
    }

    /**
     * Check if an element is associative (contains '=>' at the top level)
     */
    private function isAssociativeElement(string $element): bool
    {
        $bracketDepth = 0;
        $insideQuotes = false;
        $quoteChar = '';

        $length = strlen($element);

        for ($i = 0; $i < $length - 1; $i++) {
            $char = $element[$i];
            $nextChar = $element[$i + 1];
            $prevChar = $i > 0 ? $element[$i - 1] : '';

            // Handle quotes
            if (($char === '"' || $char === "'") && $prevChar !== '\\') {
                if ($insideQuotes && $char === $quoteChar) {
                    $insideQuotes = false;
                    $quoteChar = '';
                } elseif (!$insideQuotes) {
                    $insideQuotes = true;
                    $quoteChar = $char;
                }
            }

            // Track bracket depth if outside quotes
            if (!$insideQuotes) {
                if ($char === '[') {
                    $bracketDepth++;
                } elseif ($char === ']') {
                    $bracketDepth--;
                }
            }

            // Check for '=>' at top level
            if (!$insideQuotes && $bracketDepth === 0 && $char === '=' && $nextChar === '>') {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse associative element (key => value)
     * @throws WiremockContextException
     */
    private function parseAssociativeElement(string $element): array
    {
        $bracketDepth = 0;
        $insideQuotes = false;
        $quoteChar = '';
        $arrowPos = -1;

        $length = strlen($element);

        for ($i = 0; $i < $length - 1; $i++) {
            $char = $element[$i];
            $nextChar = $element[$i + 1];
            $prevChar = $i > 0 ? $element[$i - 1] : '';

            // Handle quotes
            if (($char === '"' || $char === "'") && $prevChar !== '\\') {
                if ($insideQuotes && $char === $quoteChar) {
                    $insideQuotes = false;
                    $quoteChar = '';
                } elseif (!$insideQuotes) {
                    $insideQuotes = true;
                    $quoteChar = $char;
                }
            }

            // Track bracket depth if outside quotes
            if (!$insideQuotes) {
                if ($char === '[') {
                    $bracketDepth++;
                } elseif ($char === ']') {
                    $bracketDepth--;
                }
            }

            // Find '=>' at top level
            if (!$insideQuotes && $bracketDepth === 0 && $char === '=' && $nextChar === '>') {
                $arrowPos = $i;
                break;
            }
        }

        if ($arrowPos === -1) {
            throw new WiremockContextException('Invalid associative array syntax');
        }

        $keyPart = trim(substr($element, 0, $arrowPos));
        $valuePart = trim(substr($element, $arrowPos + 2));

        $key = $this->convertScalarType($keyPart);
        $value = $this->convertArgumentsToPHPTyped($valuePart);

        // Ensure key is string or int for PHP array keys
        if (!is_string($key) && !is_int($key)) {
            throw new WiremockContextException('Array keys must be strings or integers');
        }

        return [$key, $value];
    }

    private function convertScalarType(string $arg): int|float|string|null|bool
    {
        // Null
        if (strtolower($arg) === 'null') return null;

        // Boolean
        if (strtolower($arg) === 'true') return true;
        if (strtolower($arg) === 'false') return false;

        // Numeric
        if (is_numeric($arg)) return str_contains($arg, '.') ? (float)$arg : (int)$arg;

        // Quoted string
        if ((str_starts_with($arg, "'") && str_ends_with($arg, "'")) ||
            (str_starts_with($arg, '"') && str_ends_with($arg, '"'))) {
            return stripslashes(substr($arg, 1, -1));
        }

        // Fallback: return as string
        return $arg;
    }
}
