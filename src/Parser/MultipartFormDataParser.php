<?php

namespace h4cc\Multipart\Parser;

use h4cc\Multipart\ParserException;

class MultipartFormDataParser extends AbstractParser
{
    const HEADER_REGEX = <<<'EOD'
        /
        (?:^|;)\s*
        (?P<name>[^=,;\s"]*)
                (?:
                    (?:="
                        (?P<quoted_value>[^"\\]*(?:\\.[^"\\]*)*)
                        ")
                    |(?:=(?<value>[^=,;\s"]*))
                )?
                /mx
EOD;

    public function parse($content)
    {
        $bodies = explode('--'.$this->getBoundary(), $content);

        // RFC says, to ignore preamble and epiloque.
        $preamble = array_shift($bodies);
        $epilogue = array_pop($bodies);

        // Need to check the first chars of epiloque, because of explode().
        if (0 !== stripos($epilogue ,"--" . static::EOL)) {
            throw new ParserException('Boundary end did not match');
        }

        $bodies = $this->parseBodies($bodies);
        $newBodies = array();

        foreach($bodies as $i => $body) {
            // RFC says, no content type means text/plain.
            if (!isset($body['headers']['content-type'])) {
                $body['content-type'] = 'text/plain';
            }
            else {
                $body['content-type'] = $body['headers']['content-type'][0];
            }

            $dispositions = array();
            if (isset($body['headers']['content-disposition'])) {
                if (preg_match_all(MultipartFormDataParser::HEADER_REGEX, $body['headers']['content-disposition'][0], $matches, PREG_SET_ORDER)) {
                    for ($i = 0; $i < count($matches); $i++) {
                        $match = $matches[$i];
                        if (!isset($match['name'])) {
                            continue;
                        }

                        if (isset($match['quoted_value'])) {
                            $value = stripcslashes($match['quoted_value']);
                        }
                        elseif (isset($match['value'])) {
                            $value = $match['value'];
                        }
                        else {
                            $value = null;
                        }

                        $dispositions[$match['name']] = $value;
                    }
                }
                $body['dispositions'] = $dispositions;
            }

            if (isset($dispositions['name'])) $name = $dispositions['name'];
            else $name = $i;

            $newBodies[$name] = $body;
        }

        return $newBodies;
    }
}
