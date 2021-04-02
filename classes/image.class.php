<?php

if (!extension_loaded('gd')) {
    error('GD Extension not loaded.');
}

class IMAGE
{
    public $Image = false;
    public $FontSize = 10;
    public $Font = '';
    public $TextAngle = 0;

    public function create($Width, $Height)
    {
        $this->Image = imagecreate($Width, $Height);
        $this->Font = SERVER_ROOT . '/classes/fonts/VERDANA.TTF';
        if (function_exists('imageantialias')) {
            imageantialias($this->Image, true);
        }
    }

    public function color($Red, $Green, $Blue, $Alpha = 0)
    {
        return imagecolorallocatealpha($this->Image, $Red, $Green, $Blue, $Alpha);
    }

    public function line($x1, $y1, $x2, $y2, $Color, $Thickness = 1)
    {
        if (1 == $Thickness) {
            return imageline($this->Image, $x1, $y1, $x2, $y2, $Color);
        }
        $t = $Thickness / 2 - 0.5;
        if ($x1 == $x2 || $y1 == $y2) {
            return imagefilledrectangle($this->Image, round(min($x1, $x2) - $t), round(min($y1, $y2) - $t), round(max($x1, $x2) + $t), round(max($y1, $y2) + $t), $color);
        }
        $k = ($y2 - $y1) / ($x2 - $x1); //y = kx + q
        $a = $t / sqrt(1 + pow($k, 2));
        $Points = [
            round($x1 - (1 + $k) * $a), round($y1 + (1 - $k) * $a),
            round($x1 - (1 - $k) * $a), round($y1 - (1 + $k) * $a),
            round($x2 + (1 + $k) * $a), round($y2 - (1 - $k) * $a),
            round($x2 + (1 - $k) * $a), round($y2 + (1 + $k) * $a),
        ];
        imagefilledpolygon($this->Image, $Points, 4, $Color);
        return imagepolygon($this->Image, $Points, 4, $Color);
    }

    public function ellipse($x, $y, $Width, $Height, $Color)
    {
        return imageEllipse($this->Image, $x, $y, $Width, $Height, $Color);
    }

    public function text($x, $y, $Color, $Text)
    {
        return imagettftext($this->Image, $this->FontSize, $this->TextAngle, $x, $y, $Color, $this->Font, $Text);
    }

    public function make_png($FileName = null)
    {
        return imagepng($this->Image, $FileName);
    }
}
