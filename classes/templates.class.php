<?php

// Example :
// $TPL = new TEMPLATE;
// $TPL->open('inv.tpl');
// $TPL->set('ADDRESS1', $TPL->str_align(57, $UADDRESS1, 'l', ' '));
// $TPL->get();

class TEMPLATE
{
    public string|array|bool $file = '';
    /**
     * @var mixed[]
     */
    public array $vars = [];
    
    public function open($file): void
    {
        $this->file = file($file);
    }
    
    public function set($name, $var, $ifnone = '<span style="font-style: italic;">-None-</span>'): void
    {
        if ('' != $name) {
            $this->vars[$name][0] = $var;
            $this->vars[$name][1] = $ifnone;
        }
    }
    
    public function show(): void
    {
        $TMPVAR = '';
        foreach ($this->file as $iValue) {
            $TMPVAR = $iValue;
            foreach ($this->vars as $k => $v) {
                if ('' != $v[1] && '' == $v[0]) {
                    $v[0] = $v[1];
                }
                $TMPVAR = str_replace('{{' . $k . '}}', $v[0], $TMPVAR);
            }
            print $TMPVAR;
        }
    }
    
    public function get(): string
    {
        $RESULT = '';
        $TMPVAR = '';
        foreach ($this->file as $iValue) {
            $TMPVAR = $iValue;
            foreach ($this->vars as $k => $v) {
                if ('' != $v[1] && '' == $v[0]) {
                    $v[0] = $v[1];
                }
                $TMPVAR = str_replace('{{' . $k . '}}', $v[0], $TMPVAR);
            }
            $RESULT .= $TMPVAR;
        }
        
        return $RESULT;
    }
    
    /**
     * @param $len
     * @param $str
     * @param $align
     * @param $fill
     *
     * @return string|mixed|void
     */
    public function str_align($len, $str, $align, $fill)
    {
        $strlen = strlen($str);
        if ($strlen > $len) {
            return substr($str, 0, $len);
        } elseif ((0 == $strlen) || (0 == $len)) {
            return '';
        } else {
            if (('l' == $align) || ('left' == $align)) {
                $result = $str . str_repeat($fill, ($len - $strlen));
            } elseif (('r' == $align) || ('right' == $align)) {
                $result = str_repeat($fill, ($len - $strlen)) . $str;
            } elseif (('c' == $align) || ('center' == $align)) {
                $snm = (int) (($len - $strlen) / 2);
                $result = ($strlen + ($snm * 2)) == $len ? str_repeat($fill, $snm) . $str : str_repeat($fill,
                        $snm + 1) . $str;
                $result .= str_repeat($fill, $snm);
            }
            
            return $result;
        }
    }
}
