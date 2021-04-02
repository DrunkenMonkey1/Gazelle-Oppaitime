<?php

// Example :
// $TPL = new TEMPLATE;
// $TPL->open('inv.tpl');
// $TPL->set('ADDRESS1', $TPL->str_align(57, $UADDRESS1, 'l', ' '));
// $TPL->get();

class TEMPLATE
{
    public $file = '';
    public $vars = [];

    public function open($file)
    {
        $this->file = file($file);
    }

    public function set($name, $var, $ifnone = '<span style="font-style: italic;">-None-</span>')
    {
        if ('' != $name) {
            $this->vars[$name][0] = $var;
            $this->vars[$name][1] = $ifnone;
        }
    }

    public function show()
    {
        $TMPVAR = '';
        for ($i = 0; $i < sizeof($this->file); $i++) {
            $TMPVAR = $this->file[$i];
            foreach ($this->vars as $k=>$v) {
                if ('' != $v[1] && '' == $v[0]) {
                    $v[0] = $v[1];
                }
                $TMPVAR = str_replace('{{' . $k . '}}', $v[0], $TMPVAR);
            }
            print $TMPVAR;
        }
    }

    public function get()
    {
        $RESULT = '';
        $TMPVAR = '';
        for ($i = 0; $i < sizeof($this->file); $i++) {
            $TMPVAR = $this->file[$i];
            foreach ($this->vars as $k=>$v) {
                if ('' != $v[1] && '' == $v[0]) {
                    $v[0] = $v[1];
                }
                $TMPVAR = str_replace('{{' . $k . '}}', $v[0], $TMPVAR);
            }
            $RESULT.= $TMPVAR;
        }
        return $RESULT;
    }

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
                $snm = intval(($len - $strlen) / 2);
                if (($strlen + ($snm * 2)) == $len) {
                    $result = str_repeat($fill, $snm) . $str;
                } else {
                    $result = str_repeat($fill, $snm + 1) . $str;
                }
                $result.= str_repeat($fill, $snm);
            }
            return $result;
        }
    }
}
