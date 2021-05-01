<?php

declare(strict_types=1);

/*-- TODO ---------------------------//
Writeup how to use the VALIDATE class, add in support for form id checks
Complete the number and date validation
Remove the GenerateJS stuff
//-----------------------------------*/

class VALIDATE
{
    /**
     * @var mixed[]
     */
    public array $Fields = [];
    
    public function SetFields($FieldName, $Required, $FieldType, $ErrorMessage, $Options = []): void
    {
        $this->Fields[$FieldName]['Type'] = strtolower($FieldType);
        $this->Fields[$FieldName]['Required'] = $Required;
        $this->Fields[$FieldName]['ErrorMessage'] = $ErrorMessage;
        if (!empty($Options['maxlength'])) {
            $this->Fields[$FieldName]['MaxLength'] = $Options['maxlength'];
        }
        if (!empty($Options['minlength'])) {
            $this->Fields[$FieldName]['MinLength'] = $Options['minlength'];
        }
        if (!empty($Options['comparefield'])) {
            $this->Fields[$FieldName]['CompareField'] = $Options['comparefield'];
        }
        if (!empty($Options['allowperiod'])) {
            $this->Fields[$FieldName]['AllowPeriod'] = $Options['allowperiod'];
        }
        if (!empty($Options['allowcomma'])) {
            $this->Fields[$FieldName]['AllowComma'] = $Options['allowcomma'];
        }
        if (!empty($Options['inarray'])) {
            $this->Fields[$FieldName]['InArray'] = $Options['inarray'];
        }
        if (!empty($Options['regex'])) {
            $this->Fields[$FieldName]['Regex'] = $Options['regex'];
        }
    }
    
    /**
     * @param $ValidateArray
     *
     * @return mixed|string|void
     */
    public function ValidateForm($ValidateArray)
    {
        reset($this->Fields);
        foreach ($this->Fields as $FieldKey => $Field) {
            $ValidateVar = $ValidateArray[$FieldKey];
            
            if ('' != $ValidateVar || !empty($Field['Required']) || 'date' === $Field['Type']) {
                if ('string' === $Field['Type']) {
                    $MaxLength = $Field['MaxLength'] ?? 255;
                    $MinLength = $Field['MinLength'] ?? 1;
                    
                    if (strlen($ValidateVar) > $MaxLength) {
                        return $Field['ErrorMessage'];
                    }
                    
                    if (strlen($ValidateVar) < $MinLength) {
                        return $Field['ErrorMessage'];
                    }
                } elseif ('number' === $Field['Type']) {
                    $MaxLength = $Field['MaxLength'] ?? '';
                    $MinLength = $Field['MinLength'] ?? 0;
                    
                    $Match = '0-9';
                    if (isset($Field['AllowPeriod'])) {
                        $Match .= '.';
                    }
                    if (isset($Field['AllowComma'])) {
                        $Match .= ',';
                    }
                    
                    if (preg_match('/[^' . $Match . ']/', $ValidateVar) || strlen($ValidateVar) < 1) {
                        return $Field['ErrorMessage'];
                    }
                    
                    if ('' != $MaxLength && $ValidateVar > $MaxLength) {
                        return $Field['ErrorMessage'] . '!!';
                    }
                    
                    if ($ValidateVar < $MinLength) {
                        return $Field['ErrorMessage'] . $MinLength;
                    }
                } elseif ('email' === $Field['Type']) {
                    $MaxLength = $Field['MaxLength'] ?? 255;
                    $MinLength = $Field['MinLength'] ?? 6;
                    
                    if (!preg_match("/^" . EMAIL_REGEX . "$/i", $ValidateVar)) {
                        return $Field['ErrorMessage'];
                    }
                    
                    if (strlen($ValidateVar) > $MaxLength) {
                        return $Field['ErrorMessage'];
                    }
                    
                    if (strlen($ValidateVar) < $MinLength) {
                        return $Field['ErrorMessage'];
                    }
                } elseif ('link' === $Field['Type']) {
                    $MaxLength = $Field['MaxLength'] ?? 255;
                    $MinLength = $Field['MinLength'] ?? 10;
                    
                    if (!preg_match('/^' . URL_REGEX . '$/i', $ValidateVar)) {
                        return $Field['ErrorMessage'];
                    }
                    
                    if (strlen($ValidateVar) > $MaxLength) {
                        return $Field['ErrorMessage'];
                    }
                    
                    if (strlen($ValidateVar) < $MinLength) {
                        return $Field['ErrorMessage'];
                    }
                } elseif ('username' === $Field['Type']) {
                    $MaxLength = $Field['MaxLength'] ?? 20;
                    $MinLength = $Field['MinLength'] ?? 1;
                    
                    if (!preg_match(USERNAME_REGEX, $ValidateVar)) {
                        return $Field['ErrorMessage'];
                    }
                    
                    if (strlen($ValidateVar) > $MaxLength) {
                        return $Field['ErrorMessage'];
                    }
                    
                    if (strlen($ValidateVar) < $MinLength) {
                        return $Field['ErrorMessage'];
                    }
                } elseif ('checkbox' === $Field['Type']) {
                    if (!isset($ValidateArray[$FieldKey])) {
                        return $Field['ErrorMessage'];
                    }
                } elseif ('compare' === $Field['Type']) {
                    if ($ValidateArray[$Field['CompareField']] != $ValidateVar) {
                        return $Field['ErrorMessage'];
                    }
                } elseif ('inarray' === $Field['Type']) {
                    if (!in_array($ValidateVar, $Field['InArray'], false)) {
                        var_dump($Field);
                        
                        return $Field['ErrorMessage'];
                    }
                } elseif ('regex' === $Field['Type']) {
                    if (!preg_match($Field['Regex'], $ValidateVar)) {
                        return $Field['ErrorMessage'];
                    }
                }
            }
        } // while
    } // function
    
    public function GenerateJS($FormID)
    {
        $ReturnJS = "<script type=\"text/javascript\" language=\"javascript\">\r\n";
        $ReturnJS .= "function formVal() {\r\n";
        $ReturnJS .= "  clearErrors('$FormID');\r\n";
        
        reset($this->Fields);
        foreach ($this->Fields as $FieldKey => $Field) {
            if ('string' == $Field['Type']) {
                $ValItem = '  if ($(\'#' . $FieldKey . '\').raw().value == ""';
                if (!empty($Field['MaxLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > ' . $Field['MaxLength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > 255';
                }
                if (!empty($Field['MinLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < ' . $Field['MinLength'];
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
            } elseif ('number' == $Field['Type']) {
                $Match = '0-9';
                if (!empty($Field['AllowPeriod'])) {
                    $Match .= '.';
                }
                if (!empty($Field['AllowComma'])) {
                    $Match .= ',';
                }
                
                $ValItem = '  if ($(\'#' . $FieldKey . '\').raw().value.match(/[^' . $Match . ']/) || $(\'#' . $FieldKey . '\').raw().value.length < 1';
                if (!empty($Field['MaxLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value/1 > ' . $Field['MaxLength'];
                }
                if (!empty($Field['MinLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value/1 < ' . $Field['MinLength'];
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
            } elseif ('email' == $Field['Type']) {
                $ValItem = '  if (!validEmail($(\'#' . $FieldKey . '\').raw().value)';
                if (!empty($Field['MaxLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > ' . $Field['MaxLength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > 255';
                }
                if (!empty($Field['MinLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < ' . $Field['MinLength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < 6';
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
            } elseif ('link' == $Field['Type']) {
                $ValItem = '  if (!validLink($(\'#' . $FieldKey . '\').raw().value)';
                if (!empty($Field['MaxLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > ' . $Field['MaxLength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > 255';
                }
                if (!empty($Field['MinLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < ' . $Field['MinLength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < 10';
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
            } elseif ('username' == $Field['Type']) {
                $ValItem = '  if ($(\'#' . $FieldKey . '\').raw().value.match(/[^a-zA-Z0-9_\-]/)';
                if (!empty($Field['MaxLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > ' . $Field['MaxLength'];
                }
                if (!empty($Field['MinLength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < ' . $Field['MinLength'];
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
            } elseif ('regex' == $Field['Type']) {
                $ValItem = '  if (!$(\'#' . $FieldKey . '\').raw().value.match(' . $Field['Regex'] . ')) { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
            } elseif ('date' == $Field['Type']) {
                $DisplayError = $FieldKey . 'month';
                if (isset($Field['MinLength']) && 3 == $Field['MinLength']) {
                    $Day = '$(\'#' . $FieldKey . 'day\').raw().value';
                    $DisplayError .= ",{$FieldKey}day";
                } else {
                    $Day = '1';
                }
                $DisplayError .= ",{$FieldKey}year";
                $ValItemHold = '  if (!validDate($(\'#' . $FieldKey . 'month\').raw().value+\'/\'+' . $Day . '+\'/\'+$(\'#' . $FieldKey . 'year\').raw().value)) { return showError(\'' . $DisplayError . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
                
                if (empty($Field['Required'])) {
                    $ValItem = '  if ($(\'#' . $FieldKey . 'month\').raw().value != ""';
                    if (isset($Field['MinLength']) && 3 == $Field['MinLength']) {
                        $ValItem .= ' || $(\'#' . $FieldKey . 'day\').raw().value != ""';
                    }
                    $ValItem .= ' || $(\'#' . $FieldKey . 'year\').raw().value != "") {' . "\r\n";
                    $ValItem .= $ValItemHold;
                    $ValItem .= " }\r\n";
                } else {
                    $ValItem .= $ValItemHold;
                }
            } elseif ('checkbox' == $Field['Type']) {
                $ValItem = '  if (!$(\'#' . $FieldKey . '\').checked) { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
            } elseif ('compare' == $Field['Type']) {
                $ValItem = '  if ($(\'#' . $FieldKey . '\').raw().value!=$(\'#' . $Field['CompareField'] . '\').raw().value) { return showError(\'' . $FieldKey . ',' . $Field['CompareField'] . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
            }
            
            if (empty($Field['Required']) && 'date' != $Field['Type']) {
                $ReturnJS .= '  if ($(\'#' . $FieldKey . '\').raw().value!="") {' . "\r\n ";
                $ReturnJS .= $ValItem;
                $ReturnJS .= "  }\r\n";
            } else {
                $ReturnJS .= $ValItem;
            }
            $ValItem = '';
        }
        
        $ReturnJS .= "}\r\n";
        $ReturnJS .= "</script>\r\n";
        
        return $ReturnJS;
    }
}
